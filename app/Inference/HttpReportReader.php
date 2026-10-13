<?php

namespace App\Inference;

use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PoliticianLink;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class HttpReportReader implements ReportReader
{
    public function __construct(
        private string $url,
        private string $key,
        private string $model,
        private int $timeout,
        private ReportReader $fallback,
    ) {}

    private ?string $used = null;

    public function method(): ?string
    {
        return $this->used;
    }

    public function read(string $text, int $subjectId, iterable $politicians, iterable $issues): array
    {
        $this->used = 'http:'.$this->model;

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->key)
                ->acceptJson()
                ->post($this->endpoint(), [
                    'model' => $this->model,
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instructions($subjectId, $politicians, $issues)],
                        ['role' => 'user', 'content' => $text],
                    ],
                ])
                ->throw()
                ->json();

            $content = $response['choices'][0]['message']['content'] ?? '';
            $decoded = json_decode($this->stripFence((string) $content), true);
            if (! is_array($decoded)) {
                return $this->fallbackRead($text, $subjectId, $politicians, $issues);
            }

            $proposals = is_array($decoded['proposals'] ?? null) ? $decoded['proposals'] : [];

            return $this->allowed($proposals, $subjectId, $politicians, $issues);
        } catch (ConnectionException|RequestException|Throwable) {
            return $this->fallbackRead($text, $subjectId, $politicians, $issues);
        }
    }

    private function endpoint(): string
    {
        $url = rtrim($this->url, '/');

        if (str_ends_with($url, '/chat/completions') || ! str_ends_with($url, '/v1')) {
            return $url;
        }

        return $url.'/chat/completions';
    }

    private function fallbackRead(string $text, int $subjectId, iterable $politicians, iterable $issues): array
    {
        $proposals = $this->fallback->read($text, $subjectId, $politicians, $issues);
        $this->used = $this->fallback->method() ?: 'local';

        return $proposals;
    }

    public function allowed(array $proposals, int $subjectId, iterable $politicians, iterable $issues): array
    {
        $politicianIds = collect($politicians)->map(fn (Politician $politician) => (int) $politician->id)->all();
        $issueNames = collect($issues)->mapWithKeys(function ($issue) {
            return [mb_strtolower($issue->name) => $issue->name];
        })->all();

        $kept = [];
        foreach ($proposals as $proposal) {
            if (! is_array($proposal)) {
                continue;
            }

            $normalized = $this->normalize($proposal, $subjectId, $politicianIds, $issueNames);
            if ($normalized !== null) {
                $kept[] = $normalized;
            }
        }

        return $kept;
    }

    private function normalize(array $proposal, int $subjectId, array $politicianIds, array $issueNames): ?array
    {
        $kind = (string) ($proposal['kind'] ?? '');

        if ($kind === 'axis') {
            $axis = (string) ($proposal['axis'] ?? '');
            if (! in_array($axis, IdeologyPosition::axisKeys(), true)) {
                return null;
            }

            $score = $this->axisScore($proposal);
            if ($score === null) {
                return null;
            }

            return ['kind' => 'axis', 'payload' => ['axis' => $axis, 'score' => $score]];
        }

        if ($kind === 'stance') {
            $name = mb_strtolower(trim((string) ($proposal['issue_name'] ?? '')));
            $position = (string) ($proposal['position'] ?? '');
            if ($name === '' || ! isset($issueNames[$name])) {
                return null;
            }
            if (! in_array($position, IssueStance::positionKeys(), true)) {
                return null;
            }

            return ['kind' => 'stance', 'payload' => [
                'issue_name' => $issueNames[$name],
                'position' => $position,
            ]];
        }

        if ($kind === 'politician_link') {
            $otherId = (int) ($proposal['other_politician_id'] ?? 0);
            $type = (string) ($proposal['type'] ?? '');
            if ($otherId === $subjectId || ! in_array($otherId, $politicianIds, true)) {
                return null;
            }
            if (! in_array($type, PoliticianLink::typeKeys(), true)) {
                return null;
            }

            return ['kind' => 'politician_link', 'payload' => [
                'other_politician_id' => $otherId,
                'type' => $type,
            ]];
        }

        return null;
    }

    private function axisScore(array $proposal): ?int
    {
        if (is_numeric($proposal['score'] ?? null)) {
            $score = (int) round((float) $proposal['score']);
        } else {
            $score = IdeologyPosition::scoreFromLegacy((string) ($proposal['position'] ?? ''));
        }

        if ($score === null || $score < 0 || $score > 100) {
            return null;
        }

        return $score;
    }

    private function instructions(int $subjectId, iterable $politicians, iterable $issues): string
    {
        $names = collect($politicians)
            ->reject(fn (Politician $politician) => (int) $politician->id === $subjectId)
            ->map(fn (Politician $politician) => $politician->id.': '.$politician->name)
            ->implode("\n");
        $issueList = collect($issues)->pluck('name')->implode("\n");

        return <<<TXT
        Read the political report and return JSON only: {"proposals":[...]}.
        Propose nothing you cannot support from the text. Do not invent people, issues, or scores.
        kind "axis" uses axis ekonomi|sosial|nasionalisme and an integer score from 0 to 100.
        0 is the left pole and 100 is the right pole. Place the score where the text supports it, not only at 0, 50, or 100.
        ekonomi: 0 Peran negara besar, 100 Orientasi pasar.
        sosial: 0 Progresif, 100 Konservatif.
        nasionalisme: 0 Pluralis, 100 Nasionalis.
        kind "stance" uses an issue name from this list and position dukung|tolak|campuran:
        {$issueList}
        kind "politician_link" uses other_politician_id from this list and type sekutu|lawan|mendukung:
        {$names}
        The report subject id is {$subjectId}. Never link the subject to itself.
        TXT;
    }

    private function stripFence(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content) ?? $content;
        }

        return trim($content);
    }
}
