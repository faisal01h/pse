<?php

namespace App\Plugins\AutoVet;

use App\Catalog\ReliabilityLevel;

class VetResult
{
    public function __construct(
        public readonly bool $checkSource,
        public readonly bool $checkWording,
        public readonly bool $checkCategory,
        public readonly bool $checkDuplicate,
        public readonly bool $checkLegalLabel,
        public readonly string $reviewNote,
        public readonly ?string $reliabilityLevel,
        public readonly string $decision,
        public readonly float $confidence,
        public readonly string $rationale,
        public readonly array $trace = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, array $trace = []): self
    {
        $decision = (string) ($data['decision'] ?? 'needs_human');
        if (! in_array($decision, ['publish', 'reject', 'needs_human'], true)) {
            $decision = 'needs_human';
        }

        $reliability = ReliabilityLevel::clampForAuto(
            isset($data['reliability_level']) ? (string) $data['reliability_level'] : null
        );

        $note = trim((string) ($data['review_note'] ?? ''));
        if (mb_strlen($note) < 40) {
            $note = trim($note.' '.((string) ($data['rationale'] ?? 'AutoVet meninjau sumber dan daftar periksa.')));
            if (mb_strlen($note) < 40) {
                $note = str_pad($note, 40, '.');
            }
        }

        return new self(
            checkSource: (bool) ($data['check_source'] ?? false),
            checkWording: (bool) ($data['check_wording'] ?? false),
            checkCategory: (bool) ($data['check_category'] ?? false),
            checkDuplicate: (bool) ($data['check_duplicate'] ?? false),
            checkLegalLabel: (bool) ($data['check_legal_label'] ?? false),
            reviewNote: mb_substr($note, 0, 5000),
            reliabilityLevel: $reliability,
            decision: $decision,
            confidence: max(0, min(1, (float) ($data['confidence'] ?? 0))),
            rationale: (string) ($data['rationale'] ?? ''),
            trace: $trace,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check_source' => $this->checkSource,
            'check_wording' => $this->checkWording,
            'check_category' => $this->checkCategory,
            'check_duplicate' => $this->checkDuplicate,
            'check_legal_label' => $this->checkLegalLabel,
            'review_note' => $this->reviewNote,
            'reliability_level' => $this->reliabilityLevel,
            'decision' => $this->decision,
            'confidence' => $this->confidence,
            'rationale' => $this->rationale,
            'trace' => $this->trace,
        ];
    }

    public function allChecksPassed(): bool
    {
        return $this->checkSource
            && $this->checkWording
            && $this->checkCategory
            && $this->checkDuplicate
            && $this->checkLegalLabel;
    }
}
