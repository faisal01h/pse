<?php

namespace App\Plugins\RealPolitik;

use App\Models\PublicRecord;
use App\Plugins\Llm\LlmService;
use App\Plugins\RealPolitik\Models\PowerSignal;
use App\Plugins\RealPolitik\Models\SituationBrief;
use App\Settings\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class SitrepAgent
{
    public function __construct(
        private LlmService $llm,
        private SettingsRepository $settings,
    ) {}

    public function generate(?Carbon $now = null): ?SituationBrief
    {
        $now ??= now();
        if (! $this->settings->getBool('realpolitik.sitrep_enabled', true)) {
            return null;
        }

        if (! $this->llm->isConfigured()) {
            return null;
        }

        $windowHours = max(1, $this->settings->getInt('realpolitik.window_hours', 24));
        $maxRecords = max(5, min(40, $this->settings->getInt('realpolitik.sitrep_max_records', 20)));
        $windowStart = $now->copy()->subHours($windowHours);

        $records = PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->where('created_at', '>=', $windowStart)
            ->with(['politician:id,name', 'topics:id,name'])
            ->orderByDesc('created_at')
            ->limit($maxRecords)
            ->get();

        if ($records->count() < 2) {
            return null;
        }

        $brief = SituationBrief::create([
            'window_start' => $windowStart,
            'window_end' => $now,
            'status' => SituationBrief::STATUS_READY,
        ]);
        $brief->records()->sync($records->pluck('id')->all());

        try {
            $parsed = $this->askLlm($records, $windowHours);
            $citations = collect($parsed['citations'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $records->contains('id', $id))
                ->unique()
                ->values();

            if ($citations->count() < 1) {
                $brief->fill([
                    'status' => SituationBrief::STATUS_LOW_CONFIDENCE,
                    'confidence' => 'low',
                    'summary_md' => 'Bukti sitasi tidak cukup untuk ringkasan REALPOLITIK.',
                    'payload' => $parsed,
                ])->save();

                return $brief;
            }

            $summary = trim((string) ($parsed['summary_md'] ?? $parsed['summary'] ?? ''));
            $brief->fill([
                'status' => ($parsed['confidence'] ?? '') === 'low'
                    ? SituationBrief::STATUS_LOW_CONFIDENCE
                    : SituationBrief::STATUS_READY,
                'confidence' => (string) ($parsed['confidence'] ?? 'medium'),
                'summary_md' => $summary !== '' ? $summary : 'Ringkasan situasional dari catatan publik terbaru.',
                'payload' => [
                    'actors' => $parsed['actors'] ?? [],
                    'stakes' => $parsed['stakes'] ?? [],
                    'power_vectors' => $parsed['power_vectors'] ?? [],
                    'watch_next' => $parsed['watch_next'] ?? [],
                    'citations' => $citations->all(),
                ],
            ])->save();

            PowerSignal::create([
                'type' => PowerSignal::TYPE_SITREP,
                'severity' => PowerSignal::SEVERITY_LOW,
                'brief_id' => $brief->id,
                'title' => 'Sitrep REALPOLITIK',
                'body' => mb_substr(strip_tags($brief->summary_md), 0, 280),
                'evidence' => ['public_record_ids' => $citations->all(), 'brief_id' => $brief->id],
            ]);
        } catch (Throwable $e) {
            $brief->fill([
                'status' => SituationBrief::STATUS_FAILED,
                'summary_md' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();
        }

        return $brief->fresh(['records']);
    }

    /**
     * @param  Collection<int, PublicRecord>  $records
     * @return array<string, mixed>
     */
    private function askLlm(Collection $records, int $windowHours): array
    {
        $snippets = $records->map(function (PublicRecord $record) {
            return [
                'id' => $record->id,
                'politician' => $record->politician?->name,
                'category' => $record->category,
                'quote' => mb_substr((string) ($record->quote ?: $record->summary), 0, 400),
                'source_type' => $record->source_type,
                'occurred_on' => optional($record->occurred_on)->toDateString(),
                'topics' => $record->topics->pluck('name')->all(),
            ];
        })->values()->all();

        $system = <<<'PROMPT'
You are REALPOLITIK for PolitiSEE, an Indonesian political archive.
Write a short power-focused situation brief using ONLY the provided public_record snippets.
Every actor claim must cite public_record ids from the input.
Return JSON only with keys:
summary_md (string, Indonesian markdown, max ~120 words),
actors (array of {name, role}),
stakes (array of strings),
power_vectors (array of {name, vector: gaining|pressured|consolidating|neutral, public_record_ids: number[]}),
watch_next (array of 2-3 falsifiable strings),
citations (number[] of public_record ids used),
confidence (high|medium|low).
If evidence is thin, set confidence=low.
PROMPT;

        $response = $this->llm->makeClient()->chat([
            [
                'role' => 'system',
                'content' => $system,
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'window_hours' => $windowHours,
                    'public_records' => $snippets,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]',
            ],
        ]);

        $content = data_get($response, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Sitrep LLM returned empty content.');
        }

        $json = $this->extractJson($content);
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Sitrep LLM returned invalid JSON.');
        }

        return $decoded;
    }

    private function extractJson(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end < $start) {
            return $content;
        }

        return substr($content, $start, $end - $start + 1);
    }
}
