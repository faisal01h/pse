<?php

namespace App\Catalog;

use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\Models\PowerSignal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RelationshipGraph
{
    public function __construct(private TemporalGraph $temporal = new TemporalGraph) {}

    public function attachPoliticians(Collection $politicians): Collection
    {
        if ($politicians->isEmpty()) {
            return $politicians;
        }

        if (! $politicians instanceof EloquentCollection) {
            $politicians = new EloquentCollection($politicians->all());
        }

        $politicians->load([
            'ideologyPositions' => fn ($query) => $query->open()->orderByDesc('effective_from')->orderByDesc('id'),
            'ideologyPositions.basisRecords' => function ($query) {
                $query->where('status', PublicRecord::STATUS_PUBLISHED)
                    ->with('topics:id,name')
                    ->orderByDesc('occurred_on')
                    ->orderByDesc('id');
            },
            'issueStances' => fn ($query) => $query->open()->orderByDesc('effective_from')->orderByDesc('id'),
            'issueStances.issue',
        ]);
        $ids = $politicians->pluck('id');
        $links = $this->temporal->currentPoliticianLinks($ids);

        $affiliations = BusinessAffiliation::query()
            ->whereIn('politician_id', $ids)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('business', fn ($q) => $q->where('status', 1))
            ->with('business:id,name,status,sector')
            ->orderBy('id')
            ->get()
            ->groupBy('politician_id');

        $currentStances = $politicians->mapWithKeys(function (Politician $politician) {
            $stances = $politician->issueStances
                ->unique(fn (IssueStance $stance) => $stance->issue_id)
                ->values();

            return [$politician->id => $stances];
        });

        $aligned = $this->alignedPoliticians(
            $currentStances->flatten()->pluck('issue_id')->unique()->filter()->values()
        );

        foreach ($politicians as $politician) {
            $stances = $currentStances->get($politician->id, collect());
            $tendencies = $politician->ideologyPositions
                ->unique(fn (IdeologyPosition $position) => $position->axis)
                ->values();

            $politician->setAttribute('tendencies', $this->publicTendencies($tendencies));
            $politician->setAttribute('connections', $this->publicPoliticianLinks($politician, $links));
            $politician->setAttribute('issue_stances', $this->publicStances(
                $stances,
                $aligned,
                'politician',
                $politician->id
            ));
            $politician->setAttribute(
                'stance_timeline',
                $this->publicStanceTimeline($this->temporal->stanceHistory($politician->id, null))
            );
            $politician->setAttribute(
                'connection_timeline',
                $this->publicPoliticianLinkTimeline($politician, $this->temporal->politicianLinkHistory($politician->id))
            );
            $politician->setAttribute(
                'business_affiliations',
                $this->publicBusinessAffiliations($affiliations->get($politician->id, collect()))
            );
            $politician->setAttribute('power_signals', $this->publicPowerSignals($politician->id));
            $politician->unsetRelation('ideologyPositions');
            $politician->unsetRelation('issueStances');
        }

        return $politicians;
    }

    public function presentParty(Party $party): Party
    {
        $party->load([
            'ideologyPositions' => fn ($query) => $query->open()->orderByDesc('effective_from')->orderByDesc('id'),
            'ideologyPositions.basisRecords' => function ($query) {
                $query->where('status', PublicRecord::STATUS_PUBLISHED)
                    ->orderByDesc('occurred_on')
                    ->orderByDesc('id');
            },
            'issueStances' => fn ($query) => $query->open()->orderByDesc('effective_from')->orderByDesc('id'),
            'issueStances.issue',
        ]);
        $links = $this->temporal->currentPartyLinks($party->id);
        $stances = $party->issueStances->unique(fn (IssueStance $stance) => $stance->issue_id)->values();
        $tendencies = $party->ideologyPositions->unique(fn (IdeologyPosition $position) => $position->axis)->values();

        $aligned = $this->alignedParties(
            $stances->pluck('issue_id')->unique()->filter()->values()
        );

        $party->setAttribute('tendencies', $this->publicTendencies($tendencies));
        $party->setAttribute('connections', $this->publicPartyLinks($party, $links));
        $party->setAttribute('issue_stances', $this->publicStances(
            $stances,
            $aligned,
            'party',
            $party->id
        ));
        $party->setAttribute(
            'stance_timeline',
            $this->publicStanceTimeline($this->temporal->stanceHistory(null, $party->id))
        );
        $party->setAttribute(
            'connection_timeline',
            $this->publicPartyLinkTimeline($party, $this->temporal->partyLinkHistory($party->id))
        );
        $party->unsetRelation('ideologyPositions');
        $party->unsetRelation('issueStances');

        return $party;
    }

    public function adminPolitician(Politician $politician): array
    {
        $links = $this->temporal->politicianLinkHistory($politician->id);
        $openLinks = $links->whereNull('ended_at')->values();

        $affiliations = BusinessAffiliation::query()
            ->where('politician_id', $politician->id)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->with('business:id,name')
            ->orderBy('id')
            ->get();

        $stanceHistory = $this->temporal->stanceHistory($politician->id, null);
        $openStances = $stanceHistory->whereNull('ended_at')->unique(fn (IssueStance $s) => $s->issue_id)->values();

        return [
            'axes' => IdeologyPosition::formAxes(),
            'link_types' => PoliticianLink::formTypes(),
            'stance_positions' => IssueStance::formPositions(),
            'affiliation_types' => BusinessAffiliation::formTypes(),
            'tendencies' => $this->adminTendencies($this->temporal->currentTendencies($politician->id, null)),
            'connections' => $openLinks->map(function (PoliticianLink $link) use ($politician) {
                $other = $link->politician_a_id === $politician->id ? $link->politicianB : $link->politicianA;

                return $this->adminConnection($link, $link->labelFrom($politician->id), $other);
            })->values()->all(),
            'stances' => $this->adminStances($openStances),
            'stance_history' => $this->adminStances($stanceHistory),
            'connection_history' => $links->map(function (PoliticianLink $link) use ($politician) {
                $other = $link->politician_a_id === $politician->id ? $link->politicianB : $link->politicianA;

                return $this->adminConnection($link, $link->labelFrom($politician->id), $other);
            })->values()->all(),
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
        $links = $this->temporal->partyLinkHistory($party->id);
        $openLinks = $links->whereNull('ended_at')->values();
        $stanceHistory = $this->temporal->stanceHistory(null, $party->id);
        $openStances = $stanceHistory->whereNull('ended_at')->unique(fn (IssueStance $s) => $s->issue_id)->values();

        return [
            'axes' => IdeologyPosition::formAxes(),
            'link_types' => PartyLink::formTypes(),
            'stance_positions' => IssueStance::formPositions(),
            'tendencies' => $this->adminTendencies($this->temporal->currentTendencies(null, $party->id)),
            'connections' => $openLinks->map(function (PartyLink $link) use ($party) {
                $other = $link->party_a_id === $party->id ? $link->partyB : $link->partyA;

                return $this->adminConnection($link, $link->labelFrom($party->id), $other);
            })->values()->all(),
            'stances' => $this->adminStances($openStances),
            'stance_history' => $this->adminStances($stanceHistory),
            'connection_history' => $links->map(function (PartyLink $link) use ($party) {
                $other = $link->party_a_id === $party->id ? $link->partyB : $link->partyA;

                return $this->adminConnection($link, $link->labelFrom($party->id), $other);
            })->values()->all(),
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

                $records = $position->relationLoaded('basisRecords')
                    ? $position->basisRecords
                    : $position->basisRecords()
                        ->where('status', PublicRecord::STATUS_PUBLISHED)
                        ->orderByDesc('occurred_on')
                        ->orderByDesc('id')
                        ->get();

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
                    'records' => $records->map(function (PublicRecord $record) {
                        $excerpt = trim((string) ($record->quote ?: $record->summary));
                        if (mb_strlen($excerpt) > 120) {
                            $excerpt = mb_substr($excerpt, 0, 117).'…';
                        }

                        return [
                            'id' => $record->id,
                            'category' => $record->category,
                            'excerpt' => $excerpt,
                            'source_url' => $record->source_url,
                            'source_type' => $record->source_type,
                            'href' => route('records.show', $record),
                            'date' => optional($record->occurred_on ?? $record->reviewed_at ?? $record->created_at)->toDateString(),
                        ];
                    })->values()->all(),
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
                'reliability_level' => $affiliation->reliability_level,
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
            ->open()
            ->whereIn('issue_id', $issueIds)
            ->whereNotNull('politician_id')
            ->whereHas('politician', function ($query) {
                $query->where('status', 1);
            })
            ->with('politician:id,name,status')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (IssueStance $stance) => $stance->politician_id.'|'.$stance->issue_id)
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
            ->open()
            ->whereIn('issue_id', $issueIds)
            ->whereNotNull('party_id')
            ->with('party:id,name')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (IssueStance $stance) => $stance->party_id.'|'.$stance->issue_id)
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
            'effective_from' => optional($position->effective_from)->toDateString() ?? '',
            'ended_at' => optional($position->ended_at)->toDateString() ?? '',
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
            'effective_from' => optional($link->effective_from)->toDateString() ?? '',
            'ended_at' => optional($link->ended_at)->toDateString() ?? '',
            'active' => $link->ended_at === null,
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
            'effective_from' => optional($stance->effective_from)->toDateString() ?? '',
            'ended_at' => optional($stance->ended_at)->toDateString() ?? '',
            'active' => $stance->ended_at === null,
            'issue' => [
                'id' => $stance->issue_id,
                'name' => $stance->issue?->name,
            ],
        ])->values()->all();
    }

    private function publicPowerSignals(int $politicianId): array
    {
        if (! app(PluginManager::class)->isEnabled('realpolitik')) {
            return [];
        }

        return PowerSignal::query()
            ->open()
            ->where('politician_id', $politicianId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (PowerSignal $signal) => [
                'id' => $signal->id,
                'type' => $signal->type,
                'severity' => $signal->severity,
                'title' => $signal->title,
                'body' => $signal->body,
                'created_at' => optional($signal->created_at)->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function publicStanceTimeline(Collection $stances): array
    {
        return $stances->map(fn (IssueStance $stance) => [
            'position_label' => $stance->positionLabel(),
            'issue' => $stance->issue?->name,
            'note' => $stance->note,
            'source_url' => $stance->source_url,
            'source_type' => $stance->source_type,
            'effective_from' => optional($stance->effective_from)->toDateString(),
            'ended_at' => optional($stance->ended_at)->toDateString(),
            'active' => $stance->ended_at === null,
        ])->values()->all();
    }

    private function publicPoliticianLinkTimeline(Politician $politician, Collection $links): array
    {
        return $links->map(function (PoliticianLink $link) use ($politician) {
            $other = $link->politician_a_id === $politician->id ? $link->politicianB : $link->politicianA;
            if (! $other || (int) $other->status !== 1) {
                return null;
            }

            return [
                'label' => $link->labelFrom($politician->id),
                'summary' => $link->summary,
                'source_url' => $link->source_url,
                'source_type' => $link->source_type,
                'effective_from' => optional($link->effective_from)->toDateString(),
                'ended_at' => optional($link->ended_at)->toDateString(),
                'active' => $link->ended_at === null,
                'other' => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'href' => route('politicians.show', $other->id),
                ],
            ];
        })->filter()->values()->all();
    }

    private function publicPartyLinkTimeline(Party $party, Collection $links): array
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
                'effective_from' => optional($link->effective_from)->toDateString(),
                'ended_at' => optional($link->ended_at)->toDateString(),
                'active' => $link->ended_at === null,
                'other' => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'href' => route('parties.show', $other->id),
                ],
            ];
        })->filter()->values()->all();
    }
}
