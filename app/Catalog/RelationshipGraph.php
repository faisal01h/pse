<?php

namespace App\Catalog;

use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RelationshipGraph
{
    public function attachPoliticians(Collection $politicians): Collection
    {
        if ($politicians->isEmpty()) {
            return $politicians;
        }

        if (! $politicians instanceof EloquentCollection) {
            $politicians = new EloquentCollection($politicians->all());
        }

        $politicians->load(['ideologyPositions', 'issueStances.issue']);
        $ids = $politicians->pluck('id');
        $links = PoliticianLink::query()
            ->where(function ($query) use ($ids) {
                $query->whereIn('politician_a_id', $ids)->orWhereIn('politician_b_id', $ids);
            })
            ->with(['politicianA:id,name,status', 'politicianB:id,name,status'])
            ->orderBy('id')
            ->get();

        $affiliations = BusinessAffiliation::query()
            ->whereIn('politician_id', $ids)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('business', fn ($q) => $q->where('status', 1))
            ->with('business:id,name,status,sector')
            ->orderBy('id')
            ->get()
            ->groupBy('politician_id');

        $aligned = $this->alignedPoliticians(
            $politicians->flatMap->issueStances->pluck('issue_id')->unique()->filter()->values()
        );

        foreach ($politicians as $politician) {
            $politician->setAttribute('tendencies', $this->publicTendencies($politician->ideologyPositions));
            $politician->setAttribute('connections', $this->publicPoliticianLinks($politician, $links));
            $politician->setAttribute('issue_stances', $this->publicStances(
                $politician->issueStances,
                $aligned,
                'politician',
                $politician->id
            ));
            $politician->setAttribute(
                'business_affiliations',
                $this->publicBusinessAffiliations($affiliations->get($politician->id, collect()))
            );
            $politician->unsetRelation('ideologyPositions');
            $politician->unsetRelation('issueStances');
        }

        return $politicians;
    }

    public function presentParty(Party $party): Party
    {
        $party->load(['ideologyPositions', 'issueStances.issue']);
        $links = PartyLink::query()
            ->where(function ($query) use ($party) {
                $query->where('party_a_id', $party->id)->orWhere('party_b_id', $party->id);
            })
            ->with(['partyA:id,name', 'partyB:id,name'])
            ->orderBy('id')
            ->get();

        $aligned = $this->alignedParties(
            $party->issueStances->pluck('issue_id')->unique()->filter()->values()
        );

        $party->setAttribute('tendencies', $this->publicTendencies($party->ideologyPositions));
        $party->setAttribute('connections', $this->publicPartyLinks($party, $links));
        $party->setAttribute('issue_stances', $this->publicStances(
            $party->issueStances,
            $aligned,
            'party',
            $party->id
        ));
        $party->unsetRelation('ideologyPositions');
        $party->unsetRelation('issueStances');

        return $party;
    }

    public function adminPolitician(Politician $politician): array
    {
        $links = PoliticianLink::query()
            ->where(function ($query) use ($politician) {
                $query->where('politician_a_id', $politician->id)->orWhere('politician_b_id', $politician->id);
            })
            ->with(['politicianA:id,name', 'politicianB:id,name'])
            ->orderBy('id')
            ->get();

        $affiliations = BusinessAffiliation::query()
            ->where('politician_id', $politician->id)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->with('business:id,name')
            ->orderBy('id')
            ->get();

        return [
            'axes' => IdeologyPosition::formAxes(),
            'link_types' => PoliticianLink::formTypes(),
            'stance_positions' => IssueStance::formPositions(),
            'affiliation_types' => BusinessAffiliation::formTypes(),
            'tendencies' => $this->adminTendencies($politician->ideologyPositions()->get()),
            'connections' => $links->map(function (PoliticianLink $link) use ($politician) {
                $other = $link->politician_a_id === $politician->id ? $link->politicianB : $link->politicianA;

                return $this->adminConnection($link, $link->labelFrom($politician->id), $other);
            })->values()->all(),
            'stances' => $this->adminStances($politician->issueStances()->with('issue')->orderBy('id')->get()),
            'affiliations' => $affiliations->map(fn (BusinessAffiliation $affiliation) => [
                'id' => $affiliation->id,
                'type' => $affiliation->type,
                'label' => $affiliation->typeLabel(),
                'ownership_percent' => $affiliation->ownership_percent,
                'summary' => $affiliation->summary,
                'source_url' => $affiliation->source_url,
                'source_type' => $affiliation->source_type,
                'occurred_on' => optional($affiliation->occurred_on)->toDateString() ?? '',
                'business' => [
                    'id' => $affiliation->business_id,
                    'name' => $affiliation->business?->name,
                ],
            ])->values()->all(),
        ];
    }

    public function adminParty(Party $party): array
    {
        $links = PartyLink::query()
            ->where(function ($query) use ($party) {
                $query->where('party_a_id', $party->id)->orWhere('party_b_id', $party->id);
            })
            ->with(['partyA:id,name', 'partyB:id,name'])
            ->orderBy('id')
            ->get();

        return [
            'axes' => IdeologyPosition::formAxes(),
            'link_types' => PartyLink::formTypes(),
            'stance_positions' => IssueStance::formPositions(),
            'tendencies' => $this->adminTendencies($party->ideologyPositions()->get()),
            'connections' => $links->map(function (PartyLink $link) use ($party) {
                $other = $link->party_a_id === $party->id ? $link->partyB : $link->partyA;

                return $this->adminConnection($link, $link->labelFrom($party->id), $other);
            })->values()->all(),
            'stances' => $this->adminStances($party->issueStances()->with('issue')->orderBy('id')->get()),
        ];
    }

    private function publicTendencies(Collection $positions): array
    {
        return collect(IdeologyPosition::axisKeys())
            ->map(function (string $axis) use ($positions) {
                $position = $positions->firstWhere('axis', $axis);
                if (! $position) {
                    return null;
                }

                $definition = IdeologyPosition::AXES[$position->axis] ?? null;

                return [
                    'axis' => $position->axis,
                    'axis_label' => $position->axisLabel(),
                    'left' => $definition['left'] ?? '',
                    'right' => $definition['right'] ?? '',
                    'score' => (int) $position->score,
                    'samples' => max(1, (int) $position->samples),
                    'note' => $position->note,
                    'source_url' => $position->source_url,
                    'source_type' => $position->source_type,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function publicBusinessAffiliations(Collection $affiliations): array
    {
        return $affiliations->map(function (BusinessAffiliation $affiliation) {
            if (! $affiliation->business) {
                return null;
            }

            return [
                'label' => $affiliation->typeLabel(),
                'type' => $affiliation->type,
                'ownership_percent' => $affiliation->ownership_percent,
                'summary' => $affiliation->summary,
                'source_url' => $affiliation->source_url,
                'source_type' => $affiliation->source_type,
                'business' => [
                    'id' => $affiliation->business->id,
                    'name' => $affiliation->business->name,
                    'href' => route('businesses.show', $affiliation->business->id),
                ],
            ];
        })->filter()->values()->all();
    }

    private function publicPoliticianLinks(Politician $politician, Collection $links): array
    {
        return $links
            ->filter(function (PoliticianLink $link) use ($politician) {
                return $link->politician_a_id === $politician->id || $link->politician_b_id === $politician->id;
            })
            ->map(function (PoliticianLink $link) use ($politician) {
                $other = $link->politician_a_id === $politician->id ? $link->politicianB : $link->politicianA;
                if (! $other || (int) $other->status !== 1) {
                    return null;
                }

                return [
                    'label' => $link->labelFrom($politician->id),
                    'summary' => $link->summary,
                    'source_url' => $link->source_url,
                    'source_type' => $link->source_type,
                    'other' => [
                        'id' => $other->id,
                        'name' => $other->name,
                        'href' => route('politicians.show', $other->id),
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function publicPartyLinks(Party $party, Collection $links): array
    {
        return $links->map(function (PartyLink $link) use ($party) {
            $other = $link->party_a_id === $party->id ? $link->partyB : $link->partyA;
            if (! $other) {
                return null;
            }

            return [
                'label' => $link->labelFrom($party->id),
                'summary' => $link->summary,
                'source_url' => $link->source_url,
                'source_type' => $link->source_type,
                'other' => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'href' => route('parties.show', $other->id),
                ],
            ];
        })->filter()->values()->all();
    }

    private function publicStances(Collection $stances, Collection $aligned, string $kind, int $subjectId): array
    {
        return $stances->map(function (IssueStance $stance) use ($aligned, $kind, $subjectId) {
            $matches = $aligned->get($stance->issue_id.'|'.$stance->position, collect())
                ->filter(fn (array $subject) => ! ($subject['kind'] === $kind && $subject['id'] === $subjectId))
                ->map(fn (array $subject) => [
                    'name' => $subject['name'],
                    'href' => $subject['href'],
                ])
                ->values()
                ->all();

            return [
                'position_label' => $stance->positionLabel(),
                'note' => $stance->note,
                'source_url' => $stance->source_url,
                'source_type' => $stance->source_type,
                'issue' => $stance->issue?->name,
                'aligned' => $matches,
            ];
        })->values()->all();
    }

    private function alignedPoliticians(Collection $issueIds): Collection
    {
        if ($issueIds->isEmpty()) {
            return collect();
        }

        return IssueStance::query()
            ->whereIn('issue_id', $issueIds)
            ->whereNotNull('politician_id')
            ->whereHas('politician', function ($query) {
                $query->where('status', 1);
            })
            ->with('politician:id,name,status')
            ->get()
            ->groupBy(fn (IssueStance $stance) => $stance->issue_id.'|'.$stance->position)
            ->map(function (Collection $group) {
                return $group->map(fn (IssueStance $stance) => [
                    'kind' => 'politician',
                    'id' => $stance->politician_id,
                    'name' => $stance->politician->name,
                    'href' => route('politicians.show', $stance->politician_id),
                ]);
            });
    }

    private function alignedParties(Collection $issueIds): Collection
    {
        if ($issueIds->isEmpty()) {
            return collect();
        }

        return IssueStance::query()
            ->whereIn('issue_id', $issueIds)
            ->whereNotNull('party_id')
            ->with('party:id,name')
            ->get()
            ->groupBy(fn (IssueStance $stance) => $stance->issue_id.'|'.$stance->position)
            ->map(function (Collection $group) {
                return $group->map(fn (IssueStance $stance) => [
                    'kind' => 'party',
                    'id' => $stance->party_id,
                    'name' => $stance->party->name,
                    'href' => route('parties.show', $stance->party_id),
                ]);
            });
    }

    private function adminTendencies(Collection $positions): array
    {
        return $positions->map(fn (IdeologyPosition $position) => [
            'axis' => $position->axis,
            'score' => (int) $position->score,
            'samples' => max(1, (int) $position->samples),
            'note' => $position->note,
            'source_url' => $position->source_url,
            'source_type' => $position->source_type,
            'occurred_on' => optional($position->occurred_on)->toDateString() ?? '',
        ])->values()->all();
    }

    private function adminConnection(PoliticianLink|PartyLink $link, string $label, Politician|Party|null $other): array
    {
        return [
            'id' => $link->id,
            'type' => $link->type,
            'label' => $label,
            'summary' => $link->summary,
            'source_url' => $link->source_url,
            'source_type' => $link->source_type,
            'occurred_on' => optional($link->occurred_on)->toDateString() ?? '',
            'other' => $other ? ['id' => $other->id, 'name' => $other->name] : null,
        ];
    }

    private function adminStances(Collection $stances): array
    {
        return $stances->map(fn (IssueStance $stance) => [
            'id' => $stance->id,
            'position' => $stance->position,
            'label' => $stance->positionLabel(),
            'note' => $stance->note,
            'source_url' => $stance->source_url,
            'source_type' => $stance->source_type,
            'occurred_on' => optional($stance->occurred_on)->toDateString() ?? '',
            'issue' => [
                'id' => $stance->issue_id,
                'name' => $stance->issue?->name,
            ],
        ])->values()->all();
    }
}
