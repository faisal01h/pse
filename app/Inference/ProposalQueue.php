<?php

namespace App\Inference;

use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\RelationshipProposal;
use Illuminate\Support\Collection;

class ProposalQueue
{
    public function pendingForRecord(int $recordId): array
    {
        return $this->present(RelationshipProposal::query()
            ->with('record.politician:id,name')
            ->where('public_record_id', $recordId)
            ->where('status', RelationshipProposal::STATUS_PENDING)
            ->orderBy('id')
            ->get());
    }

    public function pendingForPolitician(int $politicianId): array
    {
        return $this->present(RelationshipProposal::query()
            ->with('record.politician:id,name')
            ->where('status', RelationshipProposal::STATUS_PENDING)
            ->whereHas('record', fn ($query) => $query->where('politician_id', $politicianId))
            ->orderBy('id')
            ->get());
    }

    public function pending(int $limit = 20): array
    {
        return $this->present(RelationshipProposal::query()
            ->with('record.politician:id,name')
            ->where('status', RelationshipProposal::STATUS_PENDING)
            ->latest('id')
            ->limit($limit)
            ->get());
    }

    private function present(Collection $proposals): array
    {
        $politicianIds = $proposals
            ->filter(fn (RelationshipProposal $proposal) => $proposal->kind === RelationshipProposal::KIND_LINK)
            ->map(fn (RelationshipProposal $proposal) => (int) ($proposal->payload['other_politician_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();
        $names = Politician::query()->whereIn('id', $politicianIds)->pluck('name', 'id');

        return $proposals->map(function (RelationshipProposal $proposal) use ($names) {
            return [
                'id' => $proposal->id,
                'kind' => $proposal->kind,
                'label' => $this->label($proposal, $names),
                'excerpt' => $proposal->excerpt,
                'needs_note' => mb_strlen(trim($proposal->excerpt)) < 40,
                'replaces' => $this->replaces($proposal),
                'record' => [
                    'id' => $proposal->public_record_id,
                    'politician_name' => $proposal->record?->politician?->name,
                    'href' => route('reports.review', $proposal->public_record_id),
                ],
            ];
        })->values()->all();
    }

    private function label(RelationshipProposal $proposal, Collection $names): string
    {
        $payload = $proposal->payload;

        if ($proposal->kind === RelationshipProposal::KIND_AXIS) {
            $axis = IdeologyPosition::AXES[$payload['axis']]['label'] ?? $payload['axis'];

            return $axis.': '.(int) ($payload['score'] ?? 0);
        }

        if ($proposal->kind === RelationshipProposal::KIND_STANCE) {
            $position = IssueStance::LABELS[$payload['position']] ?? $payload['position'];

            return $payload['issue_name'].': '.$position;
        }

        $type = PoliticianLink::LABELS[$payload['type']] ?? $payload['type'];
        $name = $names[(int) ($payload['other_politician_id'] ?? 0)] ?? 'Politisi';

        return $type.' '.$name;
    }

    private function replaces(RelationshipProposal $proposal): bool
    {
        $politicianId = $proposal->record?->politician_id;
        if (! $politicianId) {
            return false;
        }

        if ($proposal->kind === RelationshipProposal::KIND_AXIS) {
            return IdeologyPosition::query()
                ->where('politician_id', $politicianId)
                ->where('axis', $proposal->payload['axis'] ?? '')
                ->exists();
        }

        if ($proposal->kind === RelationshipProposal::KIND_STANCE) {
            $issueId = Issue::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) ($proposal->payload['issue_name'] ?? '')))])
                ->value('id');

            return $issueId && IssueStance::query()
                ->where('politician_id', $politicianId)
                ->where('issue_id', $issueId)
                ->exists();
        }

        return false;
    }
}
