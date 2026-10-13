<?php

namespace App\Inference;

use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PoliticianLink;
use MarcReichel\Laya\Answers\Answer;
use MarcReichel\Laya\Answers\ChoiceAnswer;
use MarcReichel\Laya\Answers\ScoreAnswer;
use MarcReichel\Laya\Answers\YesNoAnswer;
use MarcReichel\Laya\Exceptions\LayaException;
use MarcReichel\Laya\Laya;
use MarcReichel\Laya\Question;

class LayaReportReader implements ReportReader
{
    private const QUESTION_LIMIT = 64;

    private const NONE = 'tidak';

    private const LEVELS = 5;

    private ?string $used = null;

    public function __construct(
        private Laya $laya,
        private ReportReader $fallback,
        private float $minConfidence = 0.55,
    ) {}

    public function method(): ?string
    {
        return $this->used;
    }

    public function read(string $text, int $subjectId, iterable $politicians, iterable $issues): array
    {
        $this->used = 'laya';
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        try {
            return $this->ask($text, $subjectId, $politicians, $issues);
        } catch (LayaException) {
            return $this->fallbackRead($text, $subjectId, $politicians, $issues);
        }
    }

    private function ask(string $text, int $subjectId, iterable $politicians, iterable $issues): array
    {
        [$questions, $issuesById] = $this->questions($subjectId, $politicians, $issues);
        $answers = [];
        $routed = null;

        foreach (array_chunk($questions, self::QUESTION_LIMIT, true) as $batch) {
            $result = $this->laya->predict($text, $batch, minConfidence: $this->threshold());
            $routed ??= $result->routedModel;
            foreach ($result as $id => $answer) {
                $answers[(string) $id] = $answer;
            }
        }

        $this->used = $routed ? 'laya:'.$routed : 'laya';

        return [
            ...$this->axes($answers),
            ...$this->stances($answers, $issuesById),
            ...$this->links($answers),
        ];
    }

    /**
     * @param  iterable<int, Politician>  $politicians
     * @param  iterable<int, Issue>  $issues
     * @return array{0: array<string, Question>, 1: array<int, string>}
     */
    private function questions(int $subjectId, iterable $politicians, iterable $issues): array
    {
        $questions = [];
        foreach (IdeologyPosition::AXES as $axis => $poles) {
            $questions['present.'.$axis] = Question::yesNo(
                "Apakah teks menempatkan subjek pada sumbu {$poles['label']} ({$poles['left']} lawan {$poles['right']})?",
                yes: 'teks mendukung satu posisi pada sumbu ini',
                no: 'teks tidak membahas sumbu ini',
            );
            $questions['score.'.$axis] = Question::score(
                "Di mana teks menempatkan subjek pada sumbu {$poles['label']}? Paling kiri adalah {$poles['left']}, paling kanan adalah {$poles['right']}.",
                $this->levels($poles['left'], $poles['right']),
            );
        }

        $issuesById = [];
        foreach ($issues as $issue) {
            if (! $issue instanceof Issue || (int) $issue->id < 1 || trim((string) $issue->name) === '') {
                continue;
            }

            $issuesById[(int) $issue->id] = $issue->name;
            $questions['stance.'.$issue->id] = Question::choice(
                "Posisi subjek terhadap isu \"{$issue->name}\". Pilih tidak bila teks tidak membahas isu ini.",
                [
                    self::NONE => 'teks tidak membahas isu ini',
                    IssueStance::SUPPORT => 'subjek mendukung isu ini',
                    IssueStance::OPPOSE => 'subjek menolak isu ini',
                    IssueStance::MIXED => 'subjek mendukung dan menolak sekaligus',
                ],
            );
        }

        foreach ($politicians as $politician) {
            if (! $politician instanceof Politician || (int) $politician->id < 1 || (int) $politician->id === $subjectId) {
                continue;
            }

            $questions['link.'.$politician->id] = Question::choice(
                "Hubungan subjek dengan {$politician->name}. Pilih tidak bila teks tidak menghubungkan keduanya.",
                [
                    self::NONE => 'tidak ada hubungan yang didukung teks',
                    PoliticianLink::TYPE_ALLY => 'sekutu atau koalisi',
                    PoliticianLink::TYPE_RIVAL => 'lawan atau kritik',
                    PoliticianLink::TYPE_SUPPORT => 'subjek mendukung orang ini',
                ],
            );
        }

        return [$questions, $issuesById];
    }

    /**
     * @param  array<string, Answer>  $answers
     */
    private function axes(array $answers): array
    {
        $proposals = [];

        foreach (array_keys(IdeologyPosition::AXES) as $axis) {
            $present = $answers['present.'.$axis] ?? null;
            $score = $answers['score.'.$axis] ?? null;
            if (! $present instanceof YesNoAnswer || ! $score instanceof ScoreAnswer) {
                continue;
            }
            if (! $this->trusted($present) || ! $present->yes() || ! $this->trusted($score)) {
                continue;
            }

            $proposals[] = [
                'kind' => 'axis',
                'payload' => [
                    'axis' => $axis,
                    'score' => $this->scoreFromLevel($score->score),
                ],
            ];
        }

        return $proposals;
    }

    /**
     * @param  array<string, Answer>  $answers
     * @param  array<int, string>  $issuesById
     */
    private function stances(array $answers, array $issuesById): array
    {
        $proposals = [];

        foreach ($issuesById as $id => $name) {
            $answer = $answers['stance.'.$id] ?? null;
            if (! $answer instanceof ChoiceAnswer || ! $this->trusted($answer)) {
                continue;
            }

            $position = (string) $answer->choice;
            if (! in_array($position, IssueStance::positionKeys(), true)) {
                continue;
            }

            $proposals[] = [
                'kind' => 'stance',
                'payload' => [
                    'issue_name' => $name,
                    'position' => $position,
                ],
            ];
        }

        return $proposals;
    }

    /**
     * @param  array<string, Answer>  $answers
     */
    private function links(array $answers): array
    {
        $proposals = [];

        foreach ($answers as $id => $answer) {
            if (! str_starts_with($id, 'link.') || ! $answer instanceof ChoiceAnswer || ! $this->trusted($answer)) {
                continue;
            }

            $type = (string) $answer->choice;
            $otherId = (int) substr($id, 5);
            if ($otherId < 1 || ! in_array($type, PoliticianLink::typeKeys(), true)) {
                continue;
            }

            $proposals[] = [
                'kind' => 'politician_link',
                'payload' => [
                    'other_politician_id' => $otherId,
                    'type' => $type,
                ],
            ];
        }

        return $proposals;
    }

    private function trusted(Answer $answer): bool
    {
        return ! $answer->lowConfidence && $answer->answerConfidence >= $this->threshold();
    }

    private function scoreFromLevel(float $expected): int
    {
        $span = self::LEVELS - 1;

        return max(0, min(100, (int) round(($expected / $span) * 100)));
    }

    /**
     * @return list<string>
     */
    private function levels(string $left, string $right): array
    {
        return [
            $left,
            'Lebih dekat ke '.$left,
            'Di antara '.$left.' dan '.$right,
            'Lebih dekat ke '.$right,
            $right,
        ];
    }

    private function threshold(): float
    {
        return max(0.0, min(1.0, $this->minConfidence));
    }

    private function fallbackRead(string $text, int $subjectId, iterable $politicians, iterable $issues): array
    {
        $proposals = $this->fallback->read($text, $subjectId, $politicians, $issues);
        $this->used = $this->fallback->method() ?: 'local';

        return $proposals;
    }
}
