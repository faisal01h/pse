<?php

namespace App\Plugins\AutoVet;

use App\Models\BusinessAffiliation;
use App\Models\PublicRecord;
use Illuminate\Support\Facades\Http;
use MarcReichel\Laya\Answers\ChoiceAnswer;
use MarcReichel\Laya\Answers\ScoreAnswer;
use MarcReichel\Laya\Answers\YesNoAnswer;
use MarcReichel\Laya\Laya;
use MarcReichel\Laya\Model;
use MarcReichel\Laya\Question;
use Throwable;

class LayaVetter
{
    public function __construct(
        private Laya $laya,
        private float $minConfidence = 0.55,
    ) {}

    /**
     * @param  array<string, mixed>  $submission
     * @return array{result: VetResult, steps: int, tokens: int}
     */
    public function vet(array $submission, PublicRecord|BusinessAffiliation $subject): array
    {
        $sourceSnippet = $this->fetchSourceSnippet((string) ($subject->source_url ?? ''));
        $checkDuplicate = ! $this->hasPublishedDuplicate($subject);

        $state = $this->buildState($submission, $sourceSnippet, $checkDuplicate);
        $questions = $this->questions($submission);

        $prediction = $this->laya->predict(
            $state,
            $questions,
            model: Model::Multilingual,
            minConfidence: $this->minConfidence,
        );

        $checkSource = $this->yes($prediction->yesNo('check_source'), $sourceSnippet !== '');
        $checkWording = $this->yes($prediction->yesNo('check_wording'));
        $checkCategory = $this->yes($prediction->yesNo('check_category'));
        $checkLegalLabel = $this->yes($prediction->yesNo('check_legal_label'));

        $decision = $this->choice($prediction->choice('decision'), ['publish', 'reject', 'needs_human'], 'needs_human');
        $reliability = $this->choice($prediction->choice('reliability'), ['low', 'medium', 'high'], 'medium');
        $confidence = $this->confidenceFromScore($prediction->score('confidence'));

        if ($prediction->choice('decision')->lowConfidence
            || $prediction->yesNo('check_source')->lowConfidence
            || $prediction->yesNo('check_wording')->lowConfidence
        ) {
            $decision = 'needs_human';
            $confidence = min($confidence, 0.45);
        }

        if (! $checkDuplicate && $decision === 'publish') {
            $decision = 'needs_human';
        }

        $checks = [
            'sumber' => $checkSource,
            'kata' => $checkWording,
            'kategori' => $checkCategory,
            'duplikat' => $checkDuplicate,
            'label_hukum' => $checkLegalLabel,
        ];
        $passed = array_keys(array_filter($checks));
        $failed = array_keys(array_filter($checks, fn ($ok) => ! $ok));

        $reviewNote = sprintf(
            'AutoVet (Laya) meninjau pengajuan ini. Lolos: %s. Perlu perhatian: %s. Keputusan usulan: %s.',
            $passed === [] ? 'tidak ada' : implode(', ', $passed),
            $failed === [] ? 'tidak ada' : implode(', ', $failed),
            $decision
        );

        $result = VetResult::fromArray([
            'check_source' => $checkSource,
            'check_wording' => $checkWording,
            'check_category' => $checkCategory,
            'check_duplicate' => $checkDuplicate,
            'check_legal_label' => $checkLegalLabel,
            'review_note' => $reviewNote,
            'reliability_level' => $reliability,
            'decision' => $decision,
            'confidence' => $confidence,
            'rationale' => 'Strategi Laya (typed questions) atas teks sumber dan isi pengajuan.',
        ], [
            ['strategy' => 'laya', 'model' => $prediction->routedModel ?? Model::Multilingual->value],
        ]);

        return [
            'result' => $result,
            'steps' => 1,
            'tokens' => $prediction->inputTokens,
        ];
    }

    /**
     * @param  array<string, mixed>  $submission
     * @return array<string, Question>
     */
    private function questions(array $submission): array
    {
        $kind = (string) ($submission['kind'] ?? 'public_record');
        $categoryHint = $kind === 'business_affiliation'
            ? 'jenis afiliasi (ownership/endorsement) cocok dengan sumber'
            : 'kategori (statement/allegation/conviction) cocok dengan sumber';

        return [
            'check_source' => Question::yesNo(
                'Apakah sumber terbuka dan menyebut tokoh/peran/peristiwa yang relevan dengan pengajuan?',
                yes: 'sumber tersedia dan menyebut subjek yang tepat',
                no: 'sumber tidak terbuka atau tidak menyebut subjek',
            ),
            'check_wording' => Question::yesNo(
                'Apakah kutipan atau ringkasan pengajuan tidak menambah fakta di luar sumber?',
                yes: 'teks pengajuan setia pada sumber',
                no: 'teks pengajuan menambah atau mengubah fakta',
            ),
            'check_category' => Question::yesNo(
                "Apakah {$categoryHint}?",
                yes: 'kategori/jenis cocok',
                no: 'kategori/jenis tidak cocok',
            ),
            'check_legal_label' => Question::yesNo(
                'Jika ini dugaan, apakah teks tidak ditulis sebagai vonis yang sudah diputus? Jika bukan dugaan, jawab ya.',
                yes: 'label hukum aman',
                no: 'dugaan ditulis seperti vonis',
            ),
            'decision' => Question::choice(
                'Keputusan pemeriksaan yang paling tepat untuk pengajuan ini.',
                [
                    'publish' => 'layak diterbitkan setelah pemeriksaan otomatis',
                    'reject' => 'tolak karena jelas tidak memenuhi syarat',
                    'needs_human' => 'butuh pemeriksa manusia',
                ]
            ),
            'reliability' => Question::choice(
                'Tingkat keandalan sumber yang pantas diusulkan (jangan officially_verified).',
                [
                    'low' => 'sumber lemah atau sulit diverifikasi',
                    'medium' => 'sumber cukup jelas',
                    'high' => 'sumber kuat dan konsisten',
                ]
            ),
            'confidence' => Question::score(
                'Seberapa yakin keputusan pemeriksaan ini?',
                ['sangat ragu', 'agak ragu', 'cukup yakin', 'yakin', 'sangat yakin'],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $submission
     */
    private function buildState(array $submission, string $sourceSnippet, bool $checkDuplicate): string
    {
        $parts = [
            'Pengajuan PolitiSEE untuk diperiksa:',
            json_encode($submission, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '',
            'Pemeriksaan duplikat terbit dengan URL sumber yang sama: '.($checkDuplicate ? 'tidak ditemukan' : 'ditemukan'),
        ];

        if ($sourceSnippet !== '') {
            $parts[] = "Cuplikan teks sumber:\n".$sourceSnippet;
        } else {
            $parts[] = 'Cuplikan teks sumber: tidak tersedia (gagal diambil atau kosong).';
        }

        return implode("\n\n", $parts);
    }

    private function fetchSourceSnippet(string $url): string
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'PolitiSEE-AutoVet/1.0'])
                ->get($url);

            if (! $response->successful()) {
                return '';
            }

            $text = trim(html_entity_decode(strip_tags((string) $response->body())));
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

            return mb_substr($text, 0, 6000);
        } catch (Throwable) {
            return '';
        }
    }

    private function hasPublishedDuplicate(PublicRecord|BusinessAffiliation $subject): bool
    {
        $url = (string) $subject->source_url;
        if ($url === '') {
            return false;
        }

        if ($subject instanceof PublicRecord) {
            return PublicRecord::query()
                ->where('source_url', $url)
                ->where('status', PublicRecord::STATUS_PUBLISHED)
                ->where('id', '!=', $subject->id)
                ->exists();
        }

        return BusinessAffiliation::query()
            ->where('source_url', $url)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->where('id', '!=', $subject->id)
            ->exists();
    }

    private function yes(YesNoAnswer $answer, bool $defaultWhenUnsure = false): bool
    {
        if ($answer->lowConfidence) {
            return $defaultWhenUnsure;
        }

        return $answer->yes();
    }

    /**
     * @param  list<string>  $allowed
     */
    private function choice(ChoiceAnswer $answer, array $allowed, string $fallback): string
    {
        $value = (string) $answer->choice;
        if ($answer->lowConfidence || ! in_array($value, $allowed, true)) {
            return $fallback;
        }

        return $value;
    }

    private function confidenceFromScore(ScoreAnswer $answer): float
    {
        $levels = max(1, count($answer->legend) - 1);
        $normalized = max(0.0, min(1.0, $answer->score / $levels));

        if ($answer->lowConfidence) {
            return min($normalized, 0.4);
        }

        return $normalized;
    }
}
