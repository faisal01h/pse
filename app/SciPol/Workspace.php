<?php

namespace App\SciPol;

use App\Catalog\PageMeta;
use App\Catalog\RelationshipGraph;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\Topic;
use Illuminate\Http\Request;

class Workspace
{
    public function __construct(
        private RelationshipGraph $graph,
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
}
