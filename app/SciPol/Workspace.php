<?php

namespace App\SciPol;

use App\Catalog\PageMeta;
use App\Catalog\RelationshipGraph;
use App\Catalog\TemporalGraph;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\PublicRecord;
use App\Models\Topic;
use Illuminate\Http\Request;

class Workspace
{
    public function __construct(
        private RelationshipGraph $graph,
        private TemporalGraph $temporal,
        private MaskedId $maskedId,
    ) {}

    /**
     * @return list<array{field: string, type: string, values: string, description: string}>
     */
    public function codebook(): array
    {
        return Codebook::rows();
    }

    /**
     * @param  list<string>  $tokens
     * @return list<int>
     */
    public function resolveTokens(array $tokens, string $type, int $max = 3): array
    {
        return $this->maskedId->decodeMany($tokens, $type, $max);
    }

    public function unmask(?string $token, string $type): ?int
    {
        if ($token === null || $token === '') {
            return null;
        }

        return $this->maskedId->decode($token, $type);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function comparePoliticians(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
        $ids = array_slice($ids, 0, 3);
        if ($ids === []) {
            return [];
        }

        $politicians = Politician::query()
            ->where('status', 1)
            ->whereIn('id', $ids)
            ->with([
                'party',
                'records' => fn ($q) => $q->where('status', PublicRecord::STATUS_PUBLISHED),
            ])
            ->get()
            ->sortBy(fn (Politician $p) => array_search($p->id, $ids, true))
            ->values();

        $this->graph->attachPoliticians($politicians);

        return $politicians->map(fn (Politician $politician) => $this->presentPolitician($politician))->all();
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function compareParties(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
        $ids = array_slice($ids, 0, 3);
        if ($ids === []) {
            return [];
        }

        $parties = Party::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Party $p) => array_search($p->id, $ids, true))
            ->values();

        return $parties->map(function (Party $party) {
            $this->graph->presentParty($party);

            return $this->presentParty($party);
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function relationshipBrowser(string $type, ?string $maskedId, ?string $asOf, bool $history): array
    {
        $type = $type === MaskedId::TYPE_PARTY ? MaskedId::TYPE_PARTY : MaskedId::TYPE_POLITICIAN;
        $empty = $this->emptyRelationshipBrowser($type, $asOf, $history);
        $id = $this->unmask($maskedId, $type);
        if ($id === null) {
            return $empty;
        }

        if ($type === MaskedId::TYPE_PARTY) {
            $party = Party::query()->find($id);
            if (! $party) {
                return $empty;
            }

            return $this->partyNeighborhood($party, $asOf, $history);
        }

        $politician = Politician::query()->where('status', 1)->find($id);
        if (! $politician) {
            return $empty;
        }

        return $this->politicianNeighborhood($politician, $asOf, $history);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function issues(): array
    {
        return Issue::query()
            ->withCount([
                'stances as politician_stance_count' => fn ($q) => $q->open()->whereNotNull('politician_id')
                    ->whereHas('politician', fn ($p) => $p->where('status', 1)),
                'stances as party_stance_count' => fn ($q) => $q->open()->whereNotNull('party_id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Issue $issue) => [
                'id' => $this->maskedId->encode(MaskedId::TYPE_ISSUE, $issue->id),
                'name' => $issue->name,
                'description' => $issue->description,
                'politician_stance_count' => (int) $issue->politician_stance_count,
                'party_stance_count' => (int) $issue->party_stance_count,
                'href' => route('scipol.issues.show', $this->maskedId->encode(MaskedId::TYPE_ISSUE, $issue->id)),
            ])
            ->all();
    }

    /**
     * @return array{issue: array<string, mixed>, groups: array<string, list<array<string, mixed>>>}
     */
    public function issueMap(Issue $issue): array
    {
        $stances = IssueStance::query()
            ->open()
            ->where('issue_id', $issue->id)
            ->with(['politician:id,name,status', 'party:id,name'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique(function (IssueStance $stance) {
                return $stance->politician_id
                    ? 'pol|'.$stance->politician_id
                    : 'party|'.$stance->party_id;
            })
            ->values();

        $groups = [
            IssueStance::SUPPORT => [],
            IssueStance::OPPOSE => [],
            IssueStance::MIXED => [],
        ];

        foreach ($stances as $stance) {
            if ($stance->politician_id) {
                if (! $stance->politician || (int) $stance->politician->status !== 1) {
                    continue;
                }
                $subject = [
                    'kind' => 'politician',
                    'id' => $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $stance->politician->id),
                    'name' => $stance->politician->name,
                    'href' => route('politicians.show', $stance->politician->id),
                ];
            } elseif ($stance->party_id && $stance->party) {
                $subject = [
                    'kind' => 'party',
                    'id' => $this->maskedId->encode(MaskedId::TYPE_PARTY, $stance->party->id),
                    'name' => $stance->party->name,
                    'href' => route('parties.show', $stance->party->id),
                ];
            } else {
                continue;
            }

            $position = $stance->position;
            if (! isset($groups[$position])) {
                continue;
            }

            $groups[$position][] = [
                'subject' => $subject,
                'position' => $position,
                'position_label' => Labels::stance($stance->position),
                'note' => $stance->note,
                'source_url' => $stance->source_url,
                'source_type' => $stance->source_type,
                'occurred_on' => optional($stance->occurred_on)->toDateString(),
                'effective_from' => optional($stance->effective_from)->toDateString(),
                'ended_at' => optional($stance->ended_at)->toDateString(),
            ];
        }

        return [
            'issue' => [
                'id' => $this->maskedId->encode(MaskedId::TYPE_ISSUE, $issue->id),
                'name' => $issue->name,
                'description' => $issue->description,
            ],
            'groups' => $groups,
            'position_labels' => Labels::STANCES,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}, filters: array<string, mixed>, sectors: list<string>}
     */
    public function affiliations(Request $request): array
    {
        $sector = trim((string) $request->query('sector', ''));
        $type = trim((string) $request->query('type', ''));
        $politicianToken = trim((string) $request->query('politician', ''));
        $politicianId = $this->unmask($politicianToken !== '' ? $politicianToken : null, MaskedId::TYPE_POLITICIAN);
        $q = trim((string) $request->query('q', ''));

        $page = BusinessAffiliation::query()
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($p) => $p->where('status', 1))
            ->whereHas('business', fn ($b) => $b->where('status', 1))
            ->when($type !== '' && array_key_exists($type, BusinessAffiliation::LABELS), fn ($query) => $query->where('type', $type))
            ->when($politicianId, fn ($query) => $query->where('politician_id', $politicianId))
            ->when($sector !== '', fn ($query) => $query->whereHas('business', fn ($b) => $b->where('sector', $sector)))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('politician', fn ($p) => $p->where('name', 'like', $like))
                        ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $like));
                });
            })
            ->with(['politician:id,name,status', 'business:id,name,sector,status'])
            ->orderByDesc('id')
            ->paginate(40);

        $sectors = Business::query()
            ->where('status', 1)
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector')
            ->values()
            ->all();

        return [
            'items' => $page->getCollection()->map(fn (BusinessAffiliation $row) => [
                'id' => $this->maskedId->encode(MaskedId::TYPE_AFFILIATION, $row->id),
                'type' => $row->type,
                'type_label' => Labels::affiliation($row->type),
                'ownership_percent' => $row->ownership_percent,
                'summary' => $row->summary,
                'source_url' => $row->source_url,
                'source_type' => $row->source_type,
                'reliability_level' => $row->reliability_level,
                'occurred_on' => optional($row->occurred_on)->toDateString(),
                'politician' => [
                    'id' => $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $row->politician->id),
                    'name' => $row->politician->name,
                    'href' => route('politicians.show', $row->politician->id),
                ],
                'business' => [
                    'id' => $this->maskedId->encode(MaskedId::TYPE_BUSINESS, $row->business->id),
                    'name' => $row->business->name,
                    'sector' => $row->business->sector,
                    'href' => route('businesses.show', $row->business->id),
                ],
            ])->values()->all(),
            'meta' => PageMeta::from($page),
            'filters' => [
                'sector' => $sector,
                'type' => $type,
                'politician' => $politicianId ? $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $politicianId) : null,
                'q' => $q,
            ],
            'sectors' => $sectors,
            'types' => collect(Labels::AFFILIATIONS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}, filters: array<string, mixed>, topics: list<array{id: int, name: string}>, categories: list<array{key: string, label: string}>}
     */
    public function topicArchive(Request $request): array
    {
        $topicToken = trim((string) $request->query('topic', ''));
        $topicId = $this->unmask($topicToken !== '' ? $topicToken : null, MaskedId::TYPE_TOPIC);
        $category = trim((string) $request->query('category', ''));
        $q = trim((string) $request->query('q', ''));

        $page = PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($p) => $p->where('status', 1))
            ->when($topicId, fn ($query) => $query->whereHas('topics', fn ($t) => $t->where('topics.id', $topicId)))
            ->when($category !== '' && in_array($category, PublicRecord::CATEGORIES, true), fn ($query) => $query->where('category', $category))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('summary', 'like', $like)
                        ->orWhere('quote', 'like', $like)
                        ->orWhereHas('politician', fn ($p) => $p->where('name', 'like', $like));
                });
            })
            ->with(['politician:id,name,status', 'topics:id,name'])
            ->orderByRaw('COALESCE(occurred_on, reviewed_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(40);

        return [
            'items' => $page->getCollection()->map(function (PublicRecord $record) {
                $date = $record->occurred_on ?? $record->reviewed_at ?? $record->created_at;

                return [
                    'id' => $this->maskedId->encode(MaskedId::TYPE_RECORD, $record->id),
                    'category' => $record->category,
                    'quote' => $record->quote,
                    'summary' => $record->summary,
                    'source_url' => $record->source_url,
                    'source_type' => $record->source_type,
                    'reliability_level' => $record->reliability_level,
                    'date' => optional($date)->toDateString(),
                    'href' => route('records.show', $record),
                    'politician' => [
                        'id' => $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $record->politician->id),
                        'name' => $record->politician->name,
                        'href' => route('politicians.show', $record->politician->id),
                    ],
                    'topics' => $record->topics->map(fn (Topic $topic) => [
                        'id' => $this->maskedId->encode(MaskedId::TYPE_TOPIC, $topic->id),
                        'name' => $topic->name,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'meta' => PageMeta::from($page),
            'filters' => [
                'topic' => $topicId ? $this->maskedId->encode(MaskedId::TYPE_TOPIC, $topicId) : null,
                'category' => $category,
                'q' => $q,
            ],
            'topics' => Topic::query()->orderBy('name')->get(['id', 'name'])->map(fn (Topic $topic) => [
                'id' => $this->maskedId->encode(MaskedId::TYPE_TOPIC, $topic->id),
                'name' => $topic->name,
            ])->values()->all(),
            'categories' => collect(Labels::RECORD_CATEGORIES)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPolitician(Politician $politician): array
    {
        $records = $politician->records ?? collect();
        $counts = [
            PublicRecord::CATEGORY_STATEMENT => 0,
            PublicRecord::CATEGORY_ALLEGATION => 0,
            PublicRecord::CATEGORY_CONVICTION => 0,
        ];
        foreach ($records as $record) {
            if (isset($counts[$record->category])) {
                $counts[$record->category]++;
            }
        }

        return [
            'kind' => 'politician',
            'id' => $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $politician->id),
            'name' => $politician->name,
            'position' => $politician->position,
            'institution' => $politician->institution,
            'href' => route('politicians.show', $politician),
            'party' => $politician->party ? [
                'name' => $politician->party->name,
                'logo_url' => $politician->party->logo_url,
            ] : null,
            'tendencies' => Labels::englishTendencies($politician->getAttribute('tendencies') ?? []),
            'issue_stances' => Labels::englishStances($politician->getAttribute('issue_stances') ?? []),
            'connections' => $this->maskConnections($politician->getAttribute('connections') ?? [], MaskedId::TYPE_POLITICIAN),
            'business_affiliations' => $this->maskAffiliationRows(
                Labels::englishAffiliations($politician->getAttribute('business_affiliations') ?? [])
            ),
            'record_counts' => $counts,
            'record_total' => array_sum($counts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentParty(Party $party): array
    {
        return [
            'kind' => 'party',
            'id' => $this->maskedId->encode(MaskedId::TYPE_PARTY, $party->id),
            'name' => $party->name,
            'logo_url' => $party->logo_url,
            'href' => route('parties.show', $party),
            'tendencies' => Labels::englishTendencies($party->getAttribute('tendencies') ?? []),
            'issue_stances' => Labels::englishStances($party->getAttribute('issue_stances') ?? []),
            'connections' => $this->maskConnections($party->getAttribute('connections') ?? [], MaskedId::TYPE_PARTY),
            'business_affiliations' => [],
            'record_counts' => null,
            'record_total' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $connections
     * @return list<array<string, mixed>>
     */
    private function maskConnections(array $connections, string $otherType): array
    {
        return array_map(function (array $connection) use ($otherType) {
            if (isset($connection['other']['id'])) {
                $connection['other']['id'] = $this->maskedId->encode($otherType, (int) $connection['other']['id']);
            }

            return $connection;
        }, $connections);
    }

    /**
     * @param  list<array<string, mixed>>  $affiliations
     * @return list<array<string, mixed>>
     */
    private function maskAffiliationRows(array $affiliations): array
    {
        return array_map(function (array $row) {
            if (isset($row['business']['id'])) {
                $row['business']['id'] = $this->maskedId->encode(MaskedId::TYPE_BUSINESS, (int) $row['business']['id']);
            }

            return $row;
        }, $affiliations);
    }

    /**
     * Build export rows for a selection/filter set.
     *
     * @return array{
     *   criteria: string,
     *   subjects: list<array<string, mixed>>,
     *   axes: list<array<string, mixed>>,
     *   stances: list<array<string, mixed>>,
     *   affiliations: list<array<string, mixed>>,
     *   records: list<array<string, mixed>>
     * }
     */
    public function exportBundle(Request $request): array
    {
        $scope = (string) $request->query('scope', 'compare');

        return match ($scope) {
            'issue' => $this->bundleFromIssue($request),
            'affiliations' => $this->bundleFromAffiliations($request),
            'topics' => $this->bundleFromTopics($request),
            default => $this->bundleFromCompare($request),
        };
    }

    private function bundleFromCompare(Request $request): array
    {
        $type = $request->query('type') === 'party' ? MaskedId::TYPE_PARTY : MaskedId::TYPE_POLITICIAN;
        $ids = $this->resolveTokens(explode(',', (string) $request->query('ids', '')), $type, 3);
        $subjects = $type === MaskedId::TYPE_PARTY
            ? $this->compareParties($ids)
            : $this->comparePoliticians($ids);
        $masked = array_map(fn (array $subject) => $subject['id'], $subjects);

        return $this->flattenSubjects($subjects, 'compare type='.$type.' refs='.implode(',', $masked));
    }

    private function bundleFromIssue(Request $request): array
    {
        $issueId = $this->unmask((string) $request->query('issue', ''), MaskedId::TYPE_ISSUE);
        abort_unless($issueId, 404);
        $issue = Issue::query()->findOrFail($issueId);
        $map = $this->issueMap($issue);
        $subjects = [];
        $stances = [];
        $axes = [];
        $affiliations = [];
        $records = [];

        foreach ($map['groups'] as $rows) {
            foreach ($rows as $row) {
                $stances[] = [
                    'subject_kind' => $row['subject']['kind'],
                    'subject_id' => $row['subject']['id'],
                    'subject_name' => $row['subject']['name'],
                    'issue' => $map['issue']['name'],
                    'position' => $row['position'],
                    'note' => $row['note'],
                    'source_url' => $row['source_url'],
                    'source_type' => $row['source_type'],
                    'occurred_on' => $row['occurred_on'],
                ];
                $subjects[$row['subject']['kind'].':'.$row['subject']['id']] = [
                    'kind' => $row['subject']['kind'],
                    'id' => $row['subject']['id'],
                    'name' => $row['subject']['name'],
                    'href' => $row['subject']['href'],
                ];
            }
        }

        return [
            'criteria' => 'issue ref='.$map['issue']['id'].' name='.$issue->name,
            'subjects' => array_values($subjects),
            'axes' => $axes,
            'stances' => $stances,
            'affiliations' => $affiliations,
            'records' => $records,
        ];
    }

    private function bundleFromAffiliations(Request $request): array
    {
        $sector = trim((string) $request->query('sector', ''));
        $type = trim((string) $request->query('type', ''));
        $politicianId = $this->unmask(trim((string) $request->query('politician', '')) ?: null, MaskedId::TYPE_POLITICIAN);
        $q = trim((string) $request->query('q', ''));

        $rows = BusinessAffiliation::query()
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($p) => $p->where('status', 1))
            ->whereHas('business', fn ($b) => $b->where('status', 1))
            ->when($type !== '' && array_key_exists($type, BusinessAffiliation::LABELS), fn ($query) => $query->where('type', $type))
            ->when($politicianId, fn ($query) => $query->where('politician_id', $politicianId))
            ->when($sector !== '', fn ($query) => $query->whereHas('business', fn ($b) => $b->where('sector', $sector)))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('politician', fn ($p) => $p->where('name', 'like', $like))
                        ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $like));
                });
            })
            ->with(['politician:id,name', 'business:id,name,sector'])
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $subjects = [];
        $affiliations = [];
        foreach ($rows as $row) {
            $politicianRef = $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $row->politician_id);
            $subjects['politician:'.$politicianRef] = [
                'kind' => 'politician',
                'id' => $politicianRef,
                'name' => $row->politician?->name,
                'href' => route('politicians.show', $row->politician_id),
            ];
            $affiliations[] = [
                'subject_kind' => 'politician',
                'subject_id' => $politicianRef,
                'subject_name' => $row->politician?->name,
                'business_id' => $this->maskedId->encode(MaskedId::TYPE_BUSINESS, $row->business_id),
                'business_name' => $row->business?->name,
                'sector' => $row->business?->sector,
                'type' => $row->type,
                'ownership_percent' => $row->ownership_percent,
                'summary' => $row->summary,
                'source_url' => $row->source_url,
                'source_type' => $row->source_type,
                'occurred_on' => optional($row->occurred_on)->toDateString(),
            ];
        }

        return [
            'criteria' => 'affiliations sector='.$sector.' type='.$type.' q='.$q,
            'subjects' => array_values($subjects),
            'axes' => [],
            'stances' => [],
            'affiliations' => $affiliations,
            'records' => [],
        ];
    }

    private function bundleFromTopics(Request $request): array
    {
        $topicId = $this->unmask(trim((string) $request->query('topic', '')) ?: null, MaskedId::TYPE_TOPIC);
        $category = trim((string) $request->query('category', ''));
        $q = trim((string) $request->query('q', ''));

        $rows = PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($p) => $p->where('status', 1))
            ->when($topicId, fn ($query) => $query->whereHas('topics', fn ($t) => $t->where('topics.id', $topicId)))
            ->when($category !== '' && in_array($category, PublicRecord::CATEGORIES, true), fn ($query) => $query->where('category', $category))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('summary', 'like', $like)
                        ->orWhere('quote', 'like', $like)
                        ->orWhereHas('politician', fn ($p) => $p->where('name', 'like', $like));
                });
            })
            ->with(['politician:id,name', 'topics:id,name'])
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $subjects = [];
        $records = [];
        foreach ($rows as $record) {
            $politicianRef = $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $record->politician_id);
            $subjects['politician:'.$politicianRef] = [
                'kind' => 'politician',
                'id' => $politicianRef,
                'name' => $record->politician?->name,
                'href' => route('politicians.show', $record->politician_id),
            ];
            $date = $record->occurred_on ?? $record->reviewed_at ?? $record->created_at;
            $records[] = [
                'record_id' => $this->maskedId->encode(MaskedId::TYPE_RECORD, $record->id),
                'subject_kind' => 'politician',
                'subject_id' => $politicianRef,
                'subject_name' => $record->politician?->name,
                'category' => $record->category,
                'summary' => $record->summary,
                'quote' => $record->quote,
                'source_url' => $record->source_url,
                'source_type' => $record->source_type,
                'date' => optional($date)->toDateString(),
                'topics' => $record->topics->pluck('name')->implode('; '),
            ];
        }

        $topicRef = $topicId ? $this->maskedId->encode(MaskedId::TYPE_TOPIC, $topicId) : '';

        return [
            'criteria' => 'topics topic='.$topicRef.' category='.$category.' q='.$q,
            'subjects' => array_values($subjects),
            'axes' => [],
            'stances' => [],
            'affiliations' => [],
            'records' => $records,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $subjects
     * @return array{
     *   criteria: string,
     *   subjects: list<array<string, mixed>>,
     *   axes: list<array<string, mixed>>,
     *   stances: list<array<string, mixed>>,
     *   affiliations: list<array<string, mixed>>,
     *   records: list<array<string, mixed>>
     * }
     */
    private function flattenSubjects(array $subjects, string $criteria): array
    {
        $axes = [];
        $stances = [];
        $affiliations = [];
        $records = [];
        $subjectRows = [];

        foreach ($subjects as $subject) {
            $subjectRows[] = [
                'kind' => $subject['kind'],
                'id' => $subject['id'],
                'name' => $subject['name'],
                'href' => $subject['href'],
                'position' => $subject['position'] ?? null,
                'institution' => $subject['institution'] ?? null,
                'party' => $subject['party']['name'] ?? null,
                'record_total' => $subject['record_total'],
            ];

            foreach ($subject['tendencies'] ?? [] as $tendency) {
                $axes[] = [
                    'subject_kind' => $subject['kind'],
                    'subject_id' => $subject['id'],
                    'subject_name' => $subject['name'],
                    'axis' => $tendency['axis'],
                    'axis_label' => $tendency['axis_label'],
                    'score' => $tendency['score'],
                    'samples' => $tendency['samples'],
                    'note' => $tendency['note'],
                    'source_url' => $tendency['source_url'],
                    'source_type' => $tendency['source_type'],
                ];
            }

            foreach ($subject['issue_stances'] ?? [] as $stance) {
                $stances[] = [
                    'subject_kind' => $subject['kind'],
                    'subject_id' => $subject['id'],
                    'subject_name' => $subject['name'],
                    'issue' => $stance['issue'],
                    'position' => $stance['position_label'],
                    'note' => $stance['note'],
                    'source_url' => $stance['source_url'],
                    'source_type' => $stance['source_type'],
                    'occurred_on' => null,
                ];
            }

            foreach ($subject['business_affiliations'] ?? [] as $affiliation) {
                $affiliations[] = [
                    'subject_kind' => $subject['kind'],
                    'subject_id' => $subject['id'],
                    'subject_name' => $subject['name'],
                    'business_id' => $affiliation['business']['id'] ?? null,
                    'business_name' => $affiliation['business']['name'] ?? null,
                    'sector' => null,
                    'type' => $affiliation['type'],
                    'ownership_percent' => $affiliation['ownership_percent'],
                    'summary' => $affiliation['summary'],
                    'source_url' => $affiliation['source_url'],
                    'source_type' => $affiliation['source_type'],
                    'occurred_on' => null,
                ];
            }

            if ($subject['kind'] === 'politician' && is_array($subject['record_counts'] ?? null)) {
                foreach ($subject['record_counts'] as $category => $count) {
                    if ($count < 1) {
                        continue;
                    }
                    $records[] = [
                        'record_id' => null,
                        'subject_kind' => 'politician',
                        'subject_id' => $subject['id'],
                        'subject_name' => $subject['name'],
                        'category' => $category,
                        'summary' => "count={$count}",
                        'quote' => null,
                        'source_url' => null,
                        'source_type' => null,
                        'date' => null,
                        'topics' => null,
                    ];
                }
            }
        }

        return [
            'criteria' => $criteria,
            'subjects' => $subjectRows,
            'axes' => $axes,
            'stances' => $stances,
            'affiliations' => $affiliations,
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRelationshipBrowser(string $type, ?string $asOf, bool $history): array
    {
        return [
            'type' => $type,
            'as_of' => $asOf,
            'history' => $history,
            'subject' => null,
            'links' => [],
            'stances' => [],
            'tendencies' => [],
            'affiliations' => $type === MaskedId::TYPE_POLITICIAN ? [] : null,
            'history_links' => [],
            'history_stances' => [],
            'history_tendencies' => [],
            'graph' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function politicianNeighborhood(Politician $politician, ?string $asOf, bool $history): array
    {
        $links = $asOf
            ? $this->temporal->politicianLinksAsOf($asOf, [$politician->id])
            : $this->temporal->currentPoliticianLinks([$politician->id]);
        $stances = $asOf
            ? $this->temporal->stancesAsOf($asOf, $politician->id, null)
            : $this->temporal->currentStances($politician->id, null);
        $tendencies = $asOf
            ? $this->temporal->tendenciesAsOf($asOf, $politician->id, null)
            : $this->temporal->currentTendencies($politician->id, null);

        $linkRows = $this->politicianLinkRows($politician, $links, $asOf, $history);
        $stanceRows = $this->stanceRows($stances);
        $tendencyRows = $this->tendencyRows($tendencies);
        $affiliationRows = $this->affiliationRows($politician);

        $historyLinks = [];
        $historyStances = [];
        $historyTendencies = [];
        if ($history) {
            $shown = collect($linkRows)->pluck('id')->all();
            $historyLinks = array_values(array_filter(
                $this->politicianLinkRows($politician, $this->temporal->politicianLinkHistory($politician->id), $asOf, $history),
                fn (array $row) => $row['ended_at'] && ! in_array($row['id'], $shown, true)
            ));
            $shownStances = collect($stanceRows)->pluck('id')->all();
            $historyStances = array_values(array_filter(
                $this->stanceRows($this->temporal->stanceHistory($politician->id, null)),
                fn (array $row) => $row['ended_at'] && ! in_array($row['id'], $shownStances, true)
            ));
            $shownAxes = collect($tendencyRows)->pluck('id')->all();
            $historyTendencies = array_values(array_filter(
                $this->tendencyRows(
                    IdeologyPosition::query()
                        ->where('politician_id', $politician->id)
                        ->whereNotNull('ended_at')
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id')
                        ->get()
                ),
                fn (array $row) => ! in_array($row['id'], $shownAxes, true)
            ));
        }

        $subject = [
            'kind' => 'politician',
            'id' => $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $politician->id),
            'name' => $politician->name,
            'href' => route('politicians.show', $politician),
        ];

        return [
            'type' => MaskedId::TYPE_POLITICIAN,
            'as_of' => $asOf,
            'history' => $history,
            'subject' => $subject,
            'links' => $linkRows,
            'stances' => $stanceRows,
            'tendencies' => $tendencyRows,
            'affiliations' => $affiliationRows,
            'history_links' => $historyLinks,
            'history_stances' => $historyStances,
            'history_tendencies' => $historyTendencies,
            'graph' => $this->relationshipGraph($subject, $linkRows, $stanceRows, $tendencyRows, $affiliationRows, $historyLinks, $historyStances, $historyTendencies, $asOf, $history),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partyNeighborhood(Party $party, ?string $asOf, bool $history): array
    {
        $links = $asOf
            ? $this->temporal->partyLinksAsOf($asOf, $party->id)
            : $this->temporal->currentPartyLinks($party->id);
        $stances = $asOf
            ? $this->temporal->stancesAsOf($asOf, null, $party->id)
            : $this->temporal->currentStances(null, $party->id);
        $tendencies = $asOf
            ? $this->temporal->tendenciesAsOf($asOf, null, $party->id)
            : $this->temporal->currentTendencies(null, $party->id);

        $linkRows = $this->partyLinkRows($party, $links, $asOf, $history);
        $stanceRows = $this->stanceRows($stances);
        $tendencyRows = $this->tendencyRows($tendencies);

        $historyLinks = [];
        $historyStances = [];
        $historyTendencies = [];
        if ($history) {
            $shown = collect($linkRows)->pluck('id')->all();
            $historyLinks = array_values(array_filter(
                $this->partyLinkRows($party, $this->temporal->partyLinkHistory($party->id), $asOf, $history),
                fn (array $row) => $row['ended_at'] && ! in_array($row['id'], $shown, true)
            ));
            $shownStances = collect($stanceRows)->pluck('id')->all();
            $historyStances = array_values(array_filter(
                $this->stanceRows($this->temporal->stanceHistory(null, $party->id)),
                fn (array $row) => $row['ended_at'] && ! in_array($row['id'], $shownStances, true)
            ));
            $shownAxes = collect($tendencyRows)->pluck('id')->all();
            $historyTendencies = array_values(array_filter(
                $this->tendencyRows(
                    IdeologyPosition::query()
                        ->where('party_id', $party->id)
                        ->whereNotNull('ended_at')
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id')
                        ->get()
                ),
                fn (array $row) => ! in_array($row['id'], $shownAxes, true)
            ));
        }

        $subject = [
            'kind' => 'party',
            'id' => $this->maskedId->encode(MaskedId::TYPE_PARTY, $party->id),
            'name' => $party->name,
            'href' => route('parties.show', $party),
        ];

        return [
            'type' => MaskedId::TYPE_PARTY,
            'as_of' => $asOf,
            'history' => $history,
            'subject' => $subject,
            'links' => $linkRows,
            'stances' => $stanceRows,
            'tendencies' => $tendencyRows,
            'affiliations' => null,
            'history_links' => $historyLinks,
            'history_stances' => $historyStances,
            'history_tendencies' => $historyTendencies,
            'graph' => $this->relationshipGraph($subject, $linkRows, $stanceRows, $tendencyRows, [], $historyLinks, $historyStances, $historyTendencies, $asOf, $history),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PoliticianLink>  $links
     * @return list<array<string, mixed>>
     */
    private function politicianLinkRows(Politician $politician, $links, ?string $asOf, bool $history): array
    {
        $rows = [];
        foreach ($links as $link) {
            $other = $link->politician_a_id === $politician->id ? $link->politicianB : $link->politicianA;
            if (! $other || (int) $other->status !== 1) {
                continue;
            }
            $masked = $this->maskedId->encode(MaskedId::TYPE_POLITICIAN, $other->id);
            $rows[] = [
                'id' => $link->id,
                'label' => Labels::politicianLink($link->type, $link->politician_a_id === $politician->id),
                'summary' => $link->summary,
                'source_url' => $link->source_url,
                'source_type' => $link->source_type,
                'effective_from' => optional($link->effective_from)->toDateString(),
                'ended_at' => optional($link->ended_at)->toDateString(),
                'other' => [
                    'kind' => 'politician',
                    'id' => $masked,
                    'name' => $other->name,
                    'href' => route('politicians.show', $other),
                    'browser_href' => $this->browserHref('politician', $masked, $asOf, $history),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PartyLink>  $links
     * @return list<array<string, mixed>>
     */
    private function partyLinkRows(Party $party, $links, ?string $asOf, bool $history): array
    {
        $rows = [];
        foreach ($links as $link) {
            $other = $link->party_a_id === $party->id ? $link->partyB : $link->partyA;
            if (! $other) {
                continue;
            }
            $masked = $this->maskedId->encode(MaskedId::TYPE_PARTY, $other->id);
            $rows[] = [
                'id' => $link->id,
                'label' => Labels::partyLink($link->type, $link->party_a_id === $party->id),
                'summary' => $link->summary,
                'source_url' => $link->source_url,
                'source_type' => $link->source_type,
                'effective_from' => optional($link->effective_from)->toDateString(),
                'ended_at' => optional($link->ended_at)->toDateString(),
                'other' => [
                    'kind' => 'party',
                    'id' => $masked,
                    'name' => $other->name,
                    'href' => route('parties.show', $other),
                    'browser_href' => $this->browserHref('party', $masked, $asOf, $history),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, IssueStance>  $stances
     * @return list<array<string, mixed>>
     */
    private function stanceRows($stances): array
    {
        return $stances->map(function (IssueStance $stance) {
            $issueId = $this->maskedId->encode(MaskedId::TYPE_ISSUE, $stance->issue_id);

            return [
                'id' => $stance->id,
                'position' => $stance->position,
                'position_label' => Labels::stance($stance->position),
                'note' => $stance->note,
                'source_url' => $stance->source_url,
                'source_type' => $stance->source_type,
                'effective_from' => optional($stance->effective_from)->toDateString(),
                'ended_at' => optional($stance->ended_at)->toDateString(),
                'issue' => [
                    'id' => $issueId,
                    'name' => $stance->issue?->name,
                    'href' => route('scipol.issues.show', $issueId),
                ],
            ];
        })->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, IdeologyPosition>  $positions
     * @return list<array<string, mixed>>
     */
    private function tendencyRows($positions): array
    {
        return $positions->map(function (IdeologyPosition $position) {
            $axis = Labels::AXES[$position->axis] ?? null;

            return [
                'id' => $position->id,
                'axis' => $position->axis,
                'axis_label' => $axis['label'] ?? $position->axisLabel(),
                'score' => (int) $position->score,
                'note' => $position->note,
                'source_url' => $position->source_url,
                'source_type' => $position->source_type,
                'effective_from' => optional($position->effective_from)->toDateString(),
                'ended_at' => optional($position->ended_at)->toDateString(),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function affiliationRows(Politician $politician): array
    {
        return BusinessAffiliation::query()
            ->where('politician_id', $politician->id)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('business', fn ($query) => $query->where('status', 1))
            ->with('business:id,name,status')
            ->orderBy('id')
            ->get()
            ->map(function (BusinessAffiliation $affiliation) {
                return [
                    'id' => $affiliation->id,
                    'label' => Labels::affiliation($affiliation->type),
                    'type' => $affiliation->type,
                    'summary' => $affiliation->summary,
                    'source_url' => $affiliation->source_url,
                    'source_type' => $affiliation->source_type,
                    'business' => [
                        'id' => $this->maskedId->encode(MaskedId::TYPE_BUSINESS, $affiliation->business_id),
                        'name' => $affiliation->business?->name,
                        'href' => route('businesses.show', $affiliation->business_id),
                    ],
                ];
            })
            ->filter(fn (array $row) => $row['business']['name'])
            ->values()
            ->all();
    }

    private function browserHref(string $type, string $maskedId, ?string $asOf, bool $history): string
    {
        $query = ['type' => $type, 'id' => $maskedId];
        if ($asOf) {
            $query['as_of'] = $asOf;
        }
        if ($history) {
            $query['history'] = 1;
        }

        return route('scipol.relationships', $query);
    }

    /**
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $links
     * @param  list<array<string, mixed>>  $stances
     * @param  list<array<string, mixed>>  $tendencies
     * @param  list<array<string, mixed>>  $affiliations
     * @param  list<array<string, mixed>>  $historyLinks
     * @param  list<array<string, mixed>>  $historyStances
     * @param  list<array<string, mixed>>  $historyTendencies
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function relationshipGraph(
        array $subject,
        array $links,
        array $stances,
        array $tendencies,
        array $affiliations,
        array $historyLinks,
        array $historyStances,
        array $historyTendencies,
        ?string $asOf,
        bool $history,
    ): array {
        $nodes = [[
            'id' => 'seed',
            'label' => $subject['name'],
            'kind' => $subject['kind'],
            'href' => null,
        ]];
        $edges = [];
        $seen = ['seed' => true];

        $addNode = function (string $id, string $label, string $kind, ?string $href) use (&$nodes, &$seen): void {
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $nodes[] = [
                'id' => $id,
                'label' => $label,
                'kind' => $kind,
                'href' => $href,
            ];
        };

        $pushLinks = function (array $rows, bool $closed) use ($addNode, &$edges, $asOf, $history): void {
            foreach ($rows as $row) {
                $nodeId = $row['other']['kind'].':'.$row['other']['id'];
                $query = ['type' => $row['other']['kind'], 'id' => $row['other']['id']];
                if ($asOf) {
                    $query['as_of'] = $asOf;
                }
                if ($history) {
                    $query['history'] = 1;
                }
                $addNode($nodeId, $row['other']['name'], $row['other']['kind'], route('scipol.relationships', $query));
                $edges[] = [
                    'id' => 'link-'.$row['id'],
                    'source' => 'seed',
                    'target' => $nodeId,
                    'label' => $row['label'],
                    'closed' => $closed || (bool) $row['ended_at'],
                ];
            }
        };

        $pushLinks($links, false);
        $pushLinks($historyLinks, true);

        $pushStances = function (array $rows, bool $closed) use ($addNode, &$edges): void {
            foreach ($rows as $row) {
                $nodeId = 'issue:'.$row['issue']['id'];
                $addNode($nodeId, (string) $row['issue']['name'], 'issue', $row['issue']['href']);
                $edges[] = [
                    'id' => 'stance-'.$row['id'],
                    'source' => 'seed',
                    'target' => $nodeId,
                    'label' => $row['position_label'],
                    'closed' => $closed || (bool) $row['ended_at'],
                ];
            }
        };
        $pushStances($stances, false);
        $pushStances($historyStances, true);

        $pushTendencies = function (array $rows, bool $closed) use ($addNode, &$edges): void {
            foreach ($rows as $row) {
                $nodeId = 'tendency:'.$row['axis'];
                $addNode($nodeId, $row['axis_label'].' '.$row['score'], 'tendency', null);
                $edges[] = [
                    'id' => 'tendency-'.$row['id'],
                    'source' => 'seed',
                    'target' => $nodeId,
                    'label' => $row['axis_label'],
                    'closed' => $closed || (bool) $row['ended_at'],
                ];
            }
        };
        $pushTendencies($tendencies, false);
        $pushTendencies($historyTendencies, true);

        foreach ($affiliations as $row) {
            $nodeId = 'business:'.$row['business']['id'];
            $addNode($nodeId, (string) $row['business']['name'], 'business', $row['business']['href']);
            $edges[] = [
                'id' => 'affiliation-'.$row['id'],
                'source' => 'seed',
                'target' => $nodeId,
                'label' => $row['label'],
                'closed' => false,
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
