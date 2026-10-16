<?php

namespace App\Http\Controllers;

use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Auditing\InferenceNotice;
use App\Catalog\PageMeta;
use App\Catalog\RelationshipGraph;
use App\Catalog\ReliabilityLevel;
use App\Inference\InferenceRunner;
use App\Inference\ProposalQueue;
use App\Plugins\AutoVet\Jobs\AutoVetJob;
use App\Plugins\AutoVet\Models\AutoVetRun;
use App\Plugins\PluginManager;
use App\Models\Audit;
use App\Models\BusinessAffiliation;
use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\RecordAttachment;
use App\Models\Topic;
use App\Security\Turnstile;
use App\Tracking\SubmissionCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PoliticianController extends Controller
{
    public function __construct(private RelationshipGraph $graph) {}

    public function apiIndex(): JsonResponse
    {
        $window = $this->summaries('', null, (int) config('catalog.api_per_page'), true);

        return response()->json([
            'data' => $window['items'],
            'meta' => $window['meta'],
        ]);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}}
     */
    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}, total: int}
     */
    public function publishedReports(?int $partyId = null, ?int $topicId = null): array
    {
        $page = PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereHas('politician', function ($query) use ($partyId) {
                $query->where('status', 1);
                if ($partyId) {
                    $query->where('party_id', $partyId);
                }
            })
            ->when($topicId, fn ($query) => $query->whereHas(
                'topics',
                fn ($topics) => $topics->where('topics.id', $topicId)
            ))
            ->with(['politician.party', 'attachments', 'topics'])
            ->orderByRaw('COALESCE(occurred_on, reviewed_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate((int) config('catalog.reports_per_page'));

        return [
            'items' => $page->getCollection()->map(function (PublicRecord $record) {
                $date = $record->occurred_on ?? $record->reviewed_at ?? $record->created_at;

                return [
                    'id' => $record->id,
                    'category' => $record->category,
                    'quote' => $record->quote,
                    'summary' => $record->summary,
                    'source_url' => $record->source_url,
                    'source_type' => $record->source_type,
                    'reliability_level' => $record->reliability_level,
                    'reliability_label' => ReliabilityLevel::label($record->reliability_level),
                    'date' => optional($date)->toDateString(),
                    'href' => route('records.show', $record),
                    'politician' => [
                        'id' => $record->politician->id,
                        'name' => $record->politician->name,
                        'photo_url' => $record->politician->photo_url,
                        'position' => $record->politician->position,
                        'institution' => $record->politician->institution,
                        'party' => $record->politician->party?->only(['id', 'name', 'logo_url']),
                    ],
                    'topics' => $record->topics->map->only(['id', 'name'])->values(),
                    'attachments' => $record->attachments->map(fn (RecordAttachment $file) => [
                        'id' => $file->id,
                        'original_name' => $file->original_name,
                        'url' => $file->url,
                        'kind' => $file->kind,
                    ])->values(),
                ];
            })->values()->all(),
            'meta' => PageMeta::from($page),
            'total' => $page->total(),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}}
     */
    public function catalog(?string $term = null): array
    {
        return $this->summaries(trim((string) $term), null, (int) config('catalog.politicians_per_page'), false);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}}
     */
    public function memberSummaries(int $partyId): array
    {
        return $this->summaries('', $partyId, (int) config('catalog.members_per_page'), true);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}}
     */
    private function summaries(string $term, ?int $partyId, int $perPage, bool $publishedRecordsOnly): array
    {
        $like = $term === '' ? null : '%'.addcslashes($term, '%_\\').'%';

        $page = Politician::query()
            ->where('status', 1)
            ->when($partyId, fn ($query) => $query->where('party_id', $partyId))
            ->when($publishedRecordsOnly, function ($query) {
                $query->whereHas('records', function ($query) {
                    $query->where('status', PublicRecord::STATUS_PUBLISHED);
                });
            })
            ->when($like, fn ($query) => $query->where('name', 'like', $like))
            ->with('party')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);

        return [
            'items' => $page->getCollection()->map(fn (Politician $politician) => [
                'id' => $politician->id,
                'name' => $politician->name,
                'photo_url' => $politician->photo_url,
                'position' => $politician->position,
                'institution' => $politician->institution,
                'href' => route('politicians.show', $politician),
                'party' => $politician->party?->only(['id', 'name', 'logo_url']),
            ])->values()->all(),
            'meta' => PageMeta::from($page),
        ];
    }

    public function show(Politician $politician)
    {
        abort_unless((int) $politician->status === 1, 404);

        $politician->load([
            'party',
            'records' => function ($query) {
                $query->where('status', 1)->with(['attachments', 'topics'])->orderByDesc('occurred_on')->orderByDesc('id');
            },
        ]);

        return $this->graph->attachPoliticians(collect([$politician]))->first();
    }

    public function pending()
    {
        return PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PENDING)
            ->with('politician.party')
            ->latest()
            ->get();
    }

    public function decided()
    {
        return PublicRecord::query()
            ->whereIn('status', [PublicRecord::STATUS_PUBLISHED, PublicRecord::STATUS_REJECTED])
            ->with(['politician.party', 'reviewer'])
            ->latest('reviewed_at')
            ->limit(20)
            ->get();
    }

    public function review(PublicRecord $record)
    {
        $record->load(['politician.party', 'reviewer', 'attachments', 'topics']);

        $related = PublicRecord::query()
            ->where('politician_id', $record->politician_id)
            ->where('id', '!=', $record->id)
            ->latest()
            ->get(['id', 'category', 'quote', 'summary', 'source_url', 'source_type', 'status', 'created_at']);

        $sameSource = PublicRecord::query()
            ->where('source_url', $record->source_url)
            ->where('id', '!=', $record->id)
            ->with('politician:id,name')
            ->get(['id', 'politician_id', 'category', 'status', 'source_url']);

        $latestRun = AutoVetRun::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->id)
            ->latest('id')
            ->first();

        return [
            'record' => $record,
            'related' => $related,
            'sameSource' => $sameSource,
            'parties' => Party::query()->orderBy('name')->get(['id', 'name']),
            'topics' => Topic::query()->orderBy('name')->pluck('name'),
            'proposals' => app(ProposalQueue::class)->pendingForRecord($record->id),
            'autovetEnabled' => app(PluginManager::class)->isEnabled('autovet'),
            'autovetRun' => $latestRun,
        ];
    }

    public function create(Request $request)
    {
        if ((int) $request->server('CONTENT_LENGTH') > 8 * 1024 * 1024) {
            return back()->withErrors([
                'media' => 'Unggahan terlalu besar. Setiap berkas maksimal 2 MB, dan seluruh formulir maksimal 8 MB.',
            ])->withInput();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'party_id' => 'nullable|string|max:20',
            'party_name' => 'nullable|string|max:255|required_if:party_id,other',
            'category' => ['required', Rule::in(PublicRecord::CATEGORIES)],
            'summary' => 'nullable|string|max:5000|required_unless:category,statement',
            'quote' => 'nullable|string|max:5000|required_if:category,statement',
            'evtype' => 'required|string|max:100',
            'link' => 'required|url|max:2048',
            'occurred_on' => 'nullable|date',
            'cf-turnstile-response' => app(Turnstile::class)->responseRule(),
            'media' => 'nullable|array|max:3',
            'media.*' => 'file|max:2048|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,application/pdf',
        ], [
            'party_name.required_if' => 'Ketik nama partai jika memilih lainnya.',
            'summary.required_unless' => 'Ringkasan wajib diisi untuk dugaan atau vonis.',
            'quote.required_if' => 'Kutipan wajib diisi untuk pernyataan publik.',
            'cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.',
            'media.max' => 'Maksimal 3 berkas.',
            'media.*.max' => 'Setiap berkas maksimal 2 MB.',
            'media.*.mimetypes' => 'Berkas harus berupa foto, video MP4 atau WebM, atau PDF.',
        ]);

        $validator->after(function ($validator) use ($request) {
            app(Turnstile::class)->assert($request, $validator, 'report');
            $this->assertPartyChoice($request, $validator);
        });

        $validated = $validator->validate();
        $partyId = $this->partyIdFromChoice($validated['party_id'] ?? null, $validated['party_name'] ?? null);

        $politician = app(DuplicateDetector::class)->findPolitician(trim($validated['name']), trim($validated['department']));

        if (! $politician) {
            $politician = Politician::create([
                'name' => trim($validated['name']),
                'position' => $validated['position'],
                'institution' => $validated['department'],
                'party_id' => $partyId,
                'status' => 0,
            ]);
        }

        $record = PublicRecord::create([
            'politician_id' => $politician->id,
            'category' => $validated['category'],
            'summary' => $validated['summary'] ?? null,
            'quote' => $validated['quote'] ?? null,
            'source_url' => $validated['link'],
            'source_type' => $validated['evtype'],
            'occurred_on' => $validated['occurred_on'] ?? null,
            'status' => PublicRecord::STATUS_PENDING,
        ]);
        $code = $this->storeTrackingCode($record);

        foreach ($request->file('media', []) as $file) {
            $record->attachments()->create([
                'path' => $file->store('record-attachments', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize() ?: 0,
            ]);
        }

        if (app(PluginManager::class)->isEnabled('autovet')) {
            AutoVetJob::dispatch($record->getMorphClass(), $record->id);
        }

        return redirect()
            ->route('reports.receipt')
            ->with('submission_code', $code);
    }

    private function storeTrackingCode(PublicRecord $record): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $code = SubmissionCode::generate();
            $hash = SubmissionCode::hash($code);
            $taken = PublicRecord::query()->where('tracking_code_hash', $hash)->exists()
                || BusinessAffiliation::query()->where('tracking_code_hash', $hash)->exists();

            if ($taken) {
                continue;
            }

            $record->tracking_code_hash = $hash;
            $record->save();

            return $code;
        }

        abort(503, 'Kode pelacakan belum bisa dibuat. Buat pengajuan sekali lagi.');
    }

    public function suggest(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return response()->json([]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $matches = Politician::query()
            ->with('party:id,name')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'position', 'institution', 'party_id']);

        return response()->json($matches->map(function (Politician $politician) {
            return [
                'id' => $politician->id,
                'name' => $politician->name,
                'position' => $politician->position,
                'institution' => $politician->institution,
                'party_id' => $politician->party_id,
                'party_name' => $politician->party?->name,
            ];
        })->values());
    }

    public function decide(Request $request, PublicRecord $record)
    {
        $checks = [
            'check_source' => 'Sumber harus dibuka dan identitas orangnya cocok.',
            'check_wording' => 'Kutipan atau ringkasan harus sesuai dengan sumber.',
            'check_category' => 'Jenis catatan harus sesuai isi sumber.',
            'check_duplicate' => 'Catatan ini belum terbit dari sumber yang sama.',
            'check_legal_label' => 'Label hukum harus membedakan dugaan dari vonis.',
        ];

        $validator = Validator::make($request->all(), [
            'decision' => ['required', Rule::in(['publish', 'reject'])],
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'party_id' => 'nullable|string|max:20',
            'party_name' => 'nullable|string|max:255|required_if:party_id,other',
            'category' => ['required', Rule::in(PublicRecord::CATEGORIES)],
            'summary' => 'nullable|string|max:5000|required_unless:category,statement',
            'quote' => 'nullable|string|max:5000|required_if:category,statement',
            'source_type' => 'required|string|max:100',
            'source_url' => 'required|url|max:2048',
            'occurred_on' => 'nullable|date',
            'review_note' => 'required|string|min:40|max:5000',
            'reliability_level' => ['nullable', 'string', Rule::in(ReliabilityLevel::all())],
            'check_source' => 'nullable|boolean',
            'check_wording' => 'nullable|boolean',
            'check_category' => 'nullable|boolean',
            'check_duplicate' => 'nullable|boolean',
            'check_legal_label' => 'nullable|boolean',
            'topics' => 'nullable|array|max:8',
            'topics.*' => 'required|string|max:80',
        ], [
            'topics.max' => 'Maksimal 8 topik.',
            'topics.*.max' => 'Nama topik maksimal 80 karakter.',
            'review_note.required' => 'Catatan pemeriksa wajib diisi.',
            'review_note.min' => 'Catatan pemeriksa minimal 40 karakter.',
            'summary.required_unless' => 'Ringkasan wajib diisi untuk dugaan atau vonis.',
            'quote.required_if' => 'Kutipan wajib diisi untuk pernyataan publik.',
            'party_name.required_if' => 'Ketik nama partai jika memilih lainnya.',
            'reliability_level.in' => 'Tingkat keandalan tidak valid.',
        ]);

        $validator->after(function ($validator) use ($request, $checks) {
            $this->assertPartyChoice($request, $validator);

            if ($request->input('decision') !== 'publish') {
                return;
            }

            foreach ($checks as $field => $message) {
                if (! $request->boolean($field)) {
                    $validator->errors()->add($field, $message);
                }
            }

            if (! in_array($request->input('reliability_level'), ReliabilityLevel::all(), true)) {
                $validator->errors()->add('reliability_level', 'Tingkat keandalan wajib dipilih saat menerbitkan.');
            }
        });

        $validated = $validator->validate();
        $partyId = $this->partyIdFromChoice($validated['party_id'] ?? null, $validated['party_name'] ?? null);
        $wasPublished = (int) $record->status === PublicRecord::STATUS_PUBLISHED;
        $previousQuote = $record->quote;
        $previousSummary = $record->summary;

        $record->fill([
            'category' => $validated['category'],
            'summary' => $validated['summary'] ?? null,
            'quote' => $validated['quote'] ?? null,
            'source_url' => $validated['source_url'],
            'source_type' => $validated['source_type'],
            'occurred_on' => $validated['occurred_on'] ?? null,
            'review_note' => $validated['review_note'],
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'check_source' => $request->boolean('check_source'),
            'check_wording' => $request->boolean('check_wording'),
            'check_category' => $request->boolean('check_category'),
            'check_duplicate' => $request->boolean('check_duplicate'),
            'check_legal_label' => $request->boolean('check_legal_label'),
            'reliability_level' => $validated['decision'] === 'publish'
                ? ($validated['reliability_level'] ?? null)
                : ($validated['reliability_level'] ?? $record->reliability_level),
            'status' => $validated['decision'] === 'publish'
                ? PublicRecord::STATUS_PUBLISHED
                : PublicRecord::STATUS_REJECTED,
        ]);
        $record->save();

        if ($request->exists('topics')) {
            $record->assignTopics($validated['topics'] ?? []);
        }

        $politician = $record->politician;
        $politician->fill([
            'name' => trim($validated['name']),
            'position' => $validated['position'],
            'institution' => $validated['department'],
            'party_id' => $partyId,
        ]);
        $stillPublished = $politician->records()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->exists()
            || $politician->businessAffiliations()
                ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
                ->exists();
        $politician->status = $stillPublished ? 1 : 0;
        $politician->save();

        $published = $validated['decision'] === 'publish';
        $textChanged = trim((string) $record->quote) !== trim((string) $previousQuote)
            || trim((string) $record->summary) !== trim((string) $previousSummary);
        $redirect = redirect()->route('dashboard');

        if ($published && (! $wasPublished || $textChanged)) {
            $audit = app(InferenceRunner::class)->run($record, $request->user()->id, Audit::TRIGGER_PUBLISH);
            $redirect->with('toast', InferenceNotice::fromAudit($audit));
        }

        return $redirect;
    }

    private function resolvePartyId(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '' || in_array(mb_strtolower($name), ['independen', 'independent'], true)) {
            return null;
        }

        $party = app(DuplicateDetector::class)->firstExact(NameNormalizer::PARTY, $name);

        if (! $party) {
            $party = Party::create(['name' => $name]);
        }

        return $party->id;
    }

    private function partyIdFromChoice(?string $partyId, ?string $partyName): ?int
    {
        if ($partyId === 'other') {
            return $this->resolvePartyId($partyName);
        }

        if ($partyId === null || $partyId === '') {
            return null;
        }

        return Party::query()->whereKey($partyId)->value('id');
    }

    private function assertPartyChoice(Request $request, \Illuminate\Validation\Validator $validator): void
    {
        $partyId = (string) $request->input('party_id', '');

        if ($partyId === '' || $partyId === 'other') {
            return;
        }

        if (! Party::query()->whereKey($partyId)->exists()) {
            $validator->errors()->add('party_id', 'Pilih partai dari daftar, atau ketik nama baru.');
        }
    }

}
