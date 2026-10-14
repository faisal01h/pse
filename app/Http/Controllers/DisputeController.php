<?php

namespace App\Http\Controllers;

use App\Models\BusinessAffiliation;
use App\Models\Dispute;
use App\Models\PublicRecord;
use App\Security\Turnstile;
use App\Tracking\SubmissionCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Dispute');
    }

    public function received(Request $request): Response
    {
        return Inertia::render('DisputeDiterima', [
            'code' => $request->session()->get('dispute_code'),
        ]);
    }

    public function records(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return PublicRecord::query()
            ->where('status', PublicRecord::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($query) => $query->where('status', 1))
            ->where(function ($query) use ($like) {
                $query->where('quote', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhereHas('politician', fn ($politician) => $politician->where('name', 'like', $like));
            })
            ->with('politician:id,name')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (PublicRecord $record) => [
                'id' => $record->id,
                'politician' => $record->politician?->name,
                'category' => $record->category,
                'excerpt' => $this->excerpt($record),
                'href' => route('politicians.show', $record->politician_id).'#record-'.$record->id,
            ])
            ->values();
    }

    public function affiliations(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return BusinessAffiliation::query()
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($query) => $query->where('status', 1))
            ->whereHas('business', fn ($query) => $query->where('status', 1))
            ->where(function ($query) use ($like) {
                $query->where('summary', 'like', $like)
                    ->orWhereHas('politician', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('business', fn ($q) => $q->where('name', 'like', $like));
            })
            ->with(['politician:id,name', 'business:id,name'])
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (BusinessAffiliation $affiliation) => [
                'id' => $affiliation->id,
                'politician' => $affiliation->politician?->name,
                'business' => $affiliation->business?->name,
                'category' => $affiliation->typeLabel(),
                'excerpt' => $affiliation->summary,
                'href' => route('businesses.show', $affiliation->business_id),
                'threshold' => $affiliation->disputeThreshold(),
                'pending_count' => $affiliation->pendingDisputesForPair(),
            ])
            ->values();
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'target' => ['required', Rule::in([Dispute::TARGET_RECORD, Dispute::TARGET_AFFILIATION])],
            'public_record_id' => 'nullable|integer|required_if:target,record',
            'business_affiliation_id' => 'nullable|integer|required_if:target,affiliation',
            'reason' => 'required|string|min:40|max:5000',
            'source_url' => ['required', 'string', 'max:2048', 'regex:/^https:\/\/\S+$/i'],
            'source_type' => 'required|string|max:100',
            'cf-turnstile-response' => 'required|string|max:2048',
        ], [
            'target.required' => 'Pilih jenis sanggahan.',
            'public_record_id.required_if' => 'Pilih arsip yang terbit.',
            'business_affiliation_id.required_if' => 'Pilih afiliasi bisnis yang terbit.',
            'reason.required' => 'Alasan sanggahan wajib diisi.',
            'reason.min' => 'Alasan sanggahan minimal 40 karakter.',
            'source_url.required' => 'Tautan sumber pembanding wajib diisi.',
            'source_url.regex' => 'Tautan sumber pembanding harus berupa https.',
            'source_type.required' => 'Jenis sumber wajib diisi.',
            'cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.',
        ]);

        $validator->after(function ($validator) use ($request) {
            app(Turnstile::class)->assert($request, $validator, 'dispute');

            if ($request->input('target') === Dispute::TARGET_RECORD) {
                $recordId = (int) $request->input('public_record_id');
                $published = PublicRecord::query()
                    ->whereKey($recordId)
                    ->where('status', PublicRecord::STATUS_PUBLISHED)
                    ->whereHas('politician', fn ($query) => $query->where('status', 1))
                    ->exists();

                if (! $published) {
                    $validator->errors()->add('public_record_id', 'Pilih arsip yang terbit.');
                }

                return;
            }

            $affiliationId = (int) $request->input('business_affiliation_id');
            $published = BusinessAffiliation::query()
                ->whereKey($affiliationId)
                ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
                ->whereHas('politician', fn ($query) => $query->where('status', 1))
                ->whereHas('business', fn ($query) => $query->where('status', 1))
                ->exists();

            if (! $published) {
                $validator->errors()->add('business_affiliation_id', 'Pilih afiliasi bisnis yang terbit.');
            }
        });

        $validated = $validator->validate();
        $code = SubmissionCode::generate();
        $isAffiliation = $validated['target'] === Dispute::TARGET_AFFILIATION;

        $dispute = Dispute::create([
            'public_record_id' => $isAffiliation ? null : (int) $validated['public_record_id'],
            'business_affiliation_id' => $isAffiliation ? (int) $validated['business_affiliation_id'] : null,
            'tracking_code' => $code,
            'reason' => trim($validated['reason']),
            'source_url' => $validated['source_url'],
            'source_type' => trim($validated['source_type']),
            'status' => Dispute::STATUS_PENDING,
        ]);

        if ($isAffiliation && $dispute->affiliation) {
            $dispute->affiliation->autoRetractIfThresholdMet();
        }

        return redirect()->route('dispute.received')->with('dispute_code', $code);
    }

    public function pending()
    {
        $disputes = Dispute::query()
            ->where('status', Dispute::STATUS_PENDING)
            ->with([
                'record.politician:id,name',
                'affiliation.politician:id,name',
                'affiliation.business:id,name',
            ])
            ->latest('id')
            ->get();

        $recordGroups = $disputes
            ->whereNotNull('public_record_id')
            ->groupBy('public_record_id')
            ->map(function ($group) {
                $record = $group->first()->record;

                return [
                    'kind' => 'record',
                    'record' => [
                        'id' => $group->first()->public_record_id,
                        'politician' => $record?->politician?->name,
                        'excerpt' => $record ? $this->excerpt($record) : '',
                        'href' => $record
                            ? route('reports.review', $group->first()->public_record_id)
                            : null,
                    ],
                    'disputes' => $this->mapDisputeRows($group),
                ];
            })
            ->values();

        $affiliationGroups = $disputes
            ->whereNotNull('business_affiliation_id')
            ->groupBy('business_affiliation_id')
            ->map(function ($group) {
                $affiliation = $group->first()->affiliation;

                return [
                    'kind' => 'affiliation',
                    'record' => [
                        'id' => $group->first()->business_affiliation_id,
                        'politician' => $affiliation?->politician?->name,
                        'excerpt' => trim(($affiliation?->business?->name ?? '').' · '.($affiliation?->typeLabel() ?? '').' — '.($affiliation?->summary ?? '')),
                        'href' => $affiliation
                            ? route('affiliations.review', $affiliation->id)
                            : null,
                        'threshold' => $affiliation?->disputeThreshold(),
                        'pending_count' => $affiliation?->pendingDisputesForPair(),
                        'retracted' => $affiliation
                            ? (int) $affiliation->status !== BusinessAffiliation::STATUS_PUBLISHED
                            : false,
                    ],
                    'disputes' => $this->mapDisputeRows($group),
                ];
            })
            ->values();

        return $recordGroups->concat($affiliationGroups)->values();
    }

    public function decide(Request $request, Dispute $dispute): RedirectResponse
    {
        abort_unless($dispute->status === Dispute::STATUS_PENDING, 404);

        $validated = $request->validate([
            'decision' => 'required|in:uphold,dismiss',
            'review_note' => 'nullable|string|max:5000',
        ]);

        $dispute->fill([
            'status' => $validated['decision'] === 'uphold' ? Dispute::STATUS_UPHELD : Dispute::STATUS_DISMISSED,
            'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);
        $dispute->save();

        if ($validated['decision'] === 'uphold') {
            if ($dispute->record) {
                $dispute->record->fill([
                    'status' => PublicRecord::STATUS_PENDING,
                    'review_note' => $dispute->review_note ?: 'Ditarik dari arsip karena sanggahan diterima.',
                    'reviewed_at' => now(),
                    'reviewed_by' => $request->user()->id,
                ]);
                $dispute->record->save();
            }

            if ($dispute->affiliation && (int) $dispute->affiliation->status === BusinessAffiliation::STATUS_PUBLISHED) {
                $affiliation = $dispute->affiliation;
                $affiliation->fill([
                    'status' => BusinessAffiliation::STATUS_PENDING,
                    'dispute_retract_pending' => true,
                    'review_note' => $dispute->review_note ?: 'Ditarik dari arsip karena sanggahan diterima.',
                    'reviewed_at' => now(),
                    'reviewed_by' => $request->user()->id,
                ]);
                $affiliation->save();

                $politician = $affiliation->politician;
                if ($politician) {
                    $stillVisible = $politician->records()
                        ->where('status', PublicRecord::STATUS_PUBLISHED)
                        ->exists()
                        || $politician->businessAffiliations()
                            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
                            ->exists();
                    $politician->status = $stillVisible ? 1 : 0;
                    $politician->save();
                }
            }
        }

        return back();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Dispute>  $group
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function mapDisputeRows($group)
    {
        return $group->map(fn (Dispute $dispute) => [
            'id' => $dispute->id,
            'tracking_code' => $dispute->tracking_code,
            'reason' => $dispute->reason,
            'source_url' => $dispute->source_url,
            'source_type' => $dispute->source_type,
            'created_at' => $dispute->created_at,
        ])->values();
    }

    private function excerpt(PublicRecord $record): string
    {
        $text = trim((string) ($record->quote ?: $record->summary));

        return mb_strlen($text) > 140 ? mb_substr($text, 0, 140).'…' : $text;
    }
}
