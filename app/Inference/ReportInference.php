<?php

namespace App\Inference;

use App\Models\Issue;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\RelationshipProposal;

class ReportInference
{
    private bool $attempted = false;

    public function __construct(private ReportReader $reader) {}

    public function method(): ?string
    {
        return $this->attempted ? $this->reader->method() : null;
    }

    public function refresh(PublicRecord $record): int
    {
        $this->attempted = false;
        if ((int) $record->status !== PublicRecord::STATUS_PUBLISHED) {
            return 0;
        }

        $excerpt = $this->excerpt($record);
        $record->proposals()->where('status', RelationshipProposal::STATUS_PENDING)->delete();

        if ($excerpt === '') {
            return 0;
        }

        $politicians = Politician::query()->orderBy('name')->get(['id', 'name']);
        $issues = Issue::query()->orderBy('name')->get(['id', 'name']);
        $kept = $record->proposals()
            ->whereIn('status', [RelationshipProposal::STATUS_ACCEPTED, RelationshipProposal::STATUS_DISMISSED])
            ->pluck('fingerprint')
            ->all();

        $created = 0;
        $this->attempted = true;

        foreach ($this->reader->read($excerpt, (int) $record->politician_id, $politicians, $issues) as $proposal) {
            $fingerprint = RelationshipProposal::fingerprint($proposal['kind'], $proposal['payload']);
            if ($fingerprint === '' || in_array($fingerprint, $kept, true)) {
                continue;
            }

            $record->proposals()->create([
                'kind' => $proposal['kind'],
                'fingerprint' => $fingerprint,
                'payload' => $proposal['payload'],
                'excerpt' => $excerpt,
                'status' => RelationshipProposal::STATUS_PENDING,
            ]);
            $kept[] = $fingerprint;
            $created++;
        }

        return $created;
    }

    public function excerpt(PublicRecord $record): string
    {
        $text = $record->category === PublicRecord::CATEGORY_STATEMENT
            ? $record->quote
            : $record->summary;

        return trim((string) $text);
    }
}
