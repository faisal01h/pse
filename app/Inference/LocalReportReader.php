<?php

namespace App\Inference;

use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PoliticianLink;
use Illuminate\Support\Collection;

class LocalReportReader implements ReportReader
{
    private const SUPPORT = ['mendukung', 'dukung'];

    private const ALLY = ['sekutu', 'koalisi'];

    private const OPPOSE = ['menentang', 'kritik', 'lawan', 'tolak'];

    private const AXES = [
        IdeologyPosition::AXIS_ECONOMIC => [
            'left' => ['peran negara', 'nasionalisasi', 'harga pokok', 'subsidi', 'bumn'],
            'right' => ['pasar bebas', 'privatisasi', 'deregulasi', 'investor'],
        ],
        IdeologyPosition::AXIS_SOCIAL => [
            'left' => ['kebebasan pers', 'kesetaraan', 'minoritas', 'ham'],
            'right' => ['keluarga tradisional', 'nilai agama', 'moral'],
        ],
        IdeologyPosition::AXIS_NATIONALISM => [
            'left' => ['kebhinekaan', 'toleransi', 'plural'],
            'right' => ['kedaulatan', 'pribumi', 'asing', 'nkri'],
        ],
    ];

    public function method(): ?string
    {
        return 'local';
    }

    public function read(string $text, int $subjectId, iterable $politicians, iterable $issues): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        return [
            ...$this->axes($text),
            ...$this->stances($text, $issues),
            ...$this->links($text, $subjectId, $politicians),
        ];
    }

    private function axes(string $text): array
    {
        $proposals = [];

        foreach (self::AXES as $axis => $sides) {
            $left = $this->hitCount($text, $sides['left']);
            $right = $this->hitCount($text, $sides['right']);
            $score = IdeologyPosition::scoreFromHits($left, $right);
            if ($score === null) {
                continue;
            }

            $proposals[] = [
                'kind' => 'axis',
                'payload' => [
                    'axis' => $axis,
                    'score' => $score,
                ],
            ];
        }

        return $proposals;
    }

    private function stances(string $text, iterable $issues): array
    {
        $support = $this->containsAny($text, self::SUPPORT);
        $oppose = $this->containsAny($text, self::OPPOSE);
        if (! $support && ! $oppose) {
            return [];
        }

        $position = $support && $oppose
            ? IssueStance::MIXED
            : ($support ? IssueStance::SUPPORT : IssueStance::OPPOSE);

        $proposals = [];
        foreach ($this->longestFirst($issues, 'name') as $issue) {
            if (! $issue instanceof Issue || ! $this->containsPhrase($text, $issue->name)) {
                continue;
            }

            $proposals[] = [
                'kind' => 'stance',
                'payload' => [
                    'issue_name' => $issue->name,
                    'position' => $position,
                ],
            ];
        }

        return $proposals;
    }

    private function links(string $text, int $subjectId, iterable $politicians): array
    {
        $type = $this->linkType($text);
        if ($type === null) {
            return [];
        }

        $named = [];
        foreach ($this->longestFirst($politicians, 'name') as $politician) {
            if (! $politician instanceof Politician || (int) $politician->id === $subjectId) {
                continue;
            }
            if (! $this->containsPhrase($text, $politician->name)) {
                continue;
            }
            if ($this->containedInLongerName($politician, $named)) {
                continue;
            }
            $named[] = $politician;
        }

        return array_map(fn (Politician $politician) => [
            'kind' => 'politician_link',
            'payload' => [
                'other_politician_id' => $politician->id,
                'type' => $type,
            ],
        ], $named);
    }

    private function linkType(string $text): ?string
    {
        $support = $this->containsAny($text, self::SUPPORT);
        $ally = $this->containsAny($text, self::ALLY);
        $oppose = $this->containsAny($text, self::OPPOSE);

        if ($support && $oppose) {
            return null;
        }
        if ($ally && $oppose) {
            return null;
        }
        if ($support) {
            return PoliticianLink::TYPE_SUPPORT;
        }
        if ($ally) {
            return PoliticianLink::TYPE_ALLY;
        }
        if ($oppose) {
            return PoliticianLink::TYPE_RIVAL;
        }

        return null;
    }

    private function hitCount(string $text, array $cues): int
    {
        $hits = 0;
        foreach ($cues as $cue) {
            if ($this->containsPhrase($text, $cue)) {
                $hits++;
            }
        }

        return $hits;
    }

    private function containsAny(string $text, array $cues): bool
    {
        foreach ($cues as $cue) {
            if ($this->containsPhrase($text, $cue)) {
                return true;
            }
        }

        return false;
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return false;
        }

        return preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/iu',
            $text
        ) === 1;
    }

    private function longestFirst(iterable $items, string $attribute): Collection
    {
        return collect($items)->sortByDesc(fn ($item) => mb_strlen((string) $item->{$attribute}))->values();
    }

    private function containedInLongerName(Politician $politician, array $longer): bool
    {
        $name = mb_strtolower($politician->name);

        foreach ($longer as $other) {
            if (str_contains(mb_strtolower($other->name), $name)) {
                return true;
            }
        }

        return false;
    }
}
