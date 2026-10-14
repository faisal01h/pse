<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\Party;
use App\Models\Politician;
use App\Security\Turnstile;
use App\Tracking\SubmissionCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BusinessAffiliationController extends Controller
{
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
            'business_id' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255|required_if:business_id,other',
            'type' => ['required', Rule::in(BusinessAffiliation::typeKeys())],
            'ownership_percent' => 'nullable|numeric|min:1|max:100',
            'ownership_unknown' => 'nullable|boolean',
            'summary' => 'required|string|max:5000',
            'evtype' => 'required|string|max:100',
            'link' => 'required|url|max:2048',
            'occurred_on' => 'nullable|date',
            'cf-turnstile-response' => 'required|string|max:2048',
            'media' => 'nullable|array|max:3',
            'media.*' => 'file|max:2048|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,application/pdf',
        ], [
            'party_name.required_if' => 'Ketik nama partai jika memilih lainnya.',
            'business_name.required_if' => 'Ketik nama bisnis jika memilih lainnya.',
            'summary.required' => 'Ringkasan afiliasi wajib diisi.',
            'cf-turnstile-response.required' => 'Selesaikan verifikasi keamanan terlebih dahulu.',
            'media.max' => 'Maksimal 3 berkas.',
            'media.*.max' => 'Setiap berkas maksimal 2 MB.',
            'media.*.mimetypes' => 'Berkas harus berupa foto, video MP4 atau WebM, atau PDF.',
        ]);

        $validator->after(function ($validator) use ($request) {
            app(Turnstile::class)->assert($request, $validator, 'affiliation');
            $this->assertPartyChoice($request, $validator);
            $this->assertBusinessChoice($request, $validator);
            $this->assertOwnershipPercent($request, $validator);
        });

        $validated = $validator->validate();
        $partyId = $this->partyIdFromChoice($validated['party_id'] ?? null, $validated['party_name'] ?? null);
        $business = $this->businessFromChoice($validated['business_id'] ?? null, $validated['business_name'] ?? null);
        $ownershipPercent = $this->ownershipPercentFromRequest($validated);

        $politician = Politician::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['name']))])
            ->whereRaw('LOWER(institution) = ?', [mb_strtolower(trim($validated['department']))])
            ->first();

        if (! $politician) {
            $politician = Politician::create([
                'name' => trim($validated['name']),
                'position' => $validated['position'],
                'institution' => $validated['department'],
                'party_id' => $partyId,
                'status' => 0,
            ]);
        }

        $affiliation = BusinessAffiliation::create([
            'politician_id' => $politician->id,
            'business_id' => $business->id,
            'type' => $validated['type'],
            'ownership_percent' => $ownershipPercent,
            'summary' => trim($validated['summary']),
            'source_url' => $validated['link'],
            'source_type' => $validated['evtype'],
            'occurred_on' => $validated['occurred_on'] ?? null,
            'status' => BusinessAffiliation::STATUS_PENDING,
        ]);
        $code = $this->storeTrackingCode($affiliation);

        foreach ($request->file('media', []) as $file) {
            $affiliation->attachments()->create([
                'path' => $file->store('affiliation-attachments', 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize() ?: 0,
            ]);
        }

        return redirect()
            ->route('reports.receipt')
            ->with('submission_code', $code);
    }

    public function pending()
    {
        return BusinessAffiliation::query()
            ->where('status', BusinessAffiliation::STATUS_PENDING)
            ->with(['politician.party', 'business'])
            ->latest()
            ->get();
    }

    public function decided()
    {
        return BusinessAffiliation::query()
            ->whereIn('status', [BusinessAffiliation::STATUS_PUBLISHED, BusinessAffiliation::STATUS_REJECTED])
            ->with(['politician.party', 'business', 'reviewer'])
            ->latest('reviewed_at')
            ->limit(20)
            ->get();
    }

    public function review(BusinessAffiliation $affiliation): array
    {
        $affiliation->load(['politician.party', 'business', 'reviewer', 'attachments']);

        $related = BusinessAffiliation::query()
            ->where('politician_id', $affiliation->politician_id)
            ->where('id', '!=', $affiliation->id)
            ->with('business:id,name')
            ->latest()
            ->get(['id', 'business_id', 'type', 'ownership_percent', 'summary', 'source_url', 'source_type', 'status', 'created_at']);

        $sameSource = BusinessAffiliation::query()
            ->where('source_url', $affiliation->source_url)
            ->where('id', '!=', $affiliation->id)
            ->with(['politician:id,name', 'business:id,name'])
            ->get(['id', 'politician_id', 'business_id', 'type', 'status', 'source_url']);

        $duplicate = BusinessAffiliation::query()
            ->where('politician_id', $affiliation->politician_id)
            ->where('business_id', $affiliation->business_id)
            ->where('type', $affiliation->type)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->where('id', '!=', $affiliation->id)
            ->exists();

        return [
            'affiliation' => $affiliation,
            'related' => $related,
            'sameSource' => $sameSource,
            'duplicatePublished' => $duplicate,
            'parties' => Party::query()->orderBy('name')->get(['id', 'name']),
            'businesses' => Business::query()->orderBy('name')->get(['id', 'name']),
            'types' => BusinessAffiliation::formTypes(),
        ];
    }

    public function decide(Request $request, BusinessAffiliation $affiliation)
    {
        $checks = [
            'check_source' => 'Sumber harus dibuka dan identitas orang serta bisnisnya cocok.',
            'check_wording' => 'Ringkasan harus sesuai dengan sumber.',
            'check_category' => 'Jenis afiliasi harus sesuai isi sumber.',
            'check_duplicate' => 'Afiliasi ini belum terbit untuk pasangan tokoh dan bisnis yang sama.',
            'check_legal_label' => 'Label kepemilikan atau endorsemen harus sesuai bukti di sumber.',
        ];

        $validator = Validator::make($request->all(), [
            'decision' => ['required', Rule::in(['publish', 'reject'])],
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'party_id' => 'nullable|string|max:20',
            'party_name' => 'nullable|string|max:255|required_if:party_id,other',
            'business_id' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255|required_if:business_id,other',
            'type' => ['required', Rule::in(BusinessAffiliation::typeKeys())],
            'ownership_percent' => 'nullable|numeric|min:1|max:100',
            'ownership_unknown' => 'nullable|boolean',
            'summary' => 'required|string|max:5000',
            'source_type' => 'required|string|max:100',
            'source_url' => 'required|url|max:2048',
            'occurred_on' => 'nullable|date',
            'review_note' => 'required|string|min:40|max:5000',
            'check_source' => 'nullable|boolean',
            'check_wording' => 'nullable|boolean',
            'check_category' => 'nullable|boolean',
            'check_duplicate' => 'nullable|boolean',
            'check_legal_label' => 'nullable|boolean',
        ], [
            'review_note.required' => 'Catatan pemeriksa wajib diisi.',
            'review_note.min' => 'Catatan pemeriksa minimal 40 karakter.',
            'party_name.required_if' => 'Ketik nama partai jika memilih lainnya.',
            'business_name.required_if' => 'Ketik nama bisnis jika memilih lainnya.',
        ]);

        $validator->after(function ($validator) use ($request, $checks, $affiliation) {
            $this->assertPartyChoice($request, $validator);
            $this->assertBusinessChoice($request, $validator);
            $this->assertOwnershipPercent($request, $validator);

            if ($request->input('decision') !== 'publish') {
                return;
            }

            foreach ($checks as $field => $message) {
                if (! $request->boolean($field)) {
                    $validator->errors()->add($field, $message);
                }
            }

            $businessId = $this->resolveBusinessIdForDuplicateCheck($request);
            if ($businessId === null) {
                return;
            }

            $duplicate = BusinessAffiliation::query()
                ->where('politician_id', $affiliation->politician_id)
                ->where('business_id', $businessId)
                ->where('type', $request->input('type'))
                ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
                ->where('id', '!=', $affiliation->id)
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('check_duplicate', 'Afiliasi terbit dengan pasangan tokoh, bisnis, dan jenis yang sama sudah ada.');
            }
        });

        $validated = $validator->validate();
        $partyId = $this->partyIdFromChoice($validated['party_id'] ?? null, $validated['party_name'] ?? null);
        $business = $this->businessFromChoice($validated['business_id'] ?? null, $validated['business_name'] ?? null);
        $ownershipPercent = $this->ownershipPercentFromRequest($validated);
        $published = $validated['decision'] === 'publish';

        if ($published) {
            $affiliation->applyRepublishAfterDisputeRetract();
        }

        $affiliation->fill([
            'business_id' => $business->id,
            'type' => $validated['type'],
            'ownership_percent' => $ownershipPercent,
            'summary' => trim($validated['summary']),
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
            'status' => $published
                ? BusinessAffiliation::STATUS_PUBLISHED
                : BusinessAffiliation::STATUS_REJECTED,
            'dispute_threshold' => $affiliation->dispute_threshold,
            'dispute_retract_pending' => $published ? false : $affiliation->dispute_retract_pending,
        ]);
        $affiliation->save();

        if ($published) {
            $business->status = Business::STATUS_PUBLISHED;
            $business->save();
        }

        $politician = $affiliation->politician;
        $politician->fill([
            'name' => trim($validated['name']),
            'position' => $validated['position'],
            'institution' => $validated['department'],
            'party_id' => $partyId,
        ]);
        $politician->status = $this->politicianShouldBePublished($politician) ? 1 : 0;
        $politician->save();

        return redirect()->route('dashboard');
    }

    private function politicianShouldBePublished(Politician $politician): bool
    {
        $hasRecords = $politician->records()
            ->where('status', \App\Models\PublicRecord::STATUS_PUBLISHED)
            ->exists();
        $hasAffiliations = $politician->businessAffiliations()
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->exists();

        return $hasRecords || $hasAffiliations;
    }

    private function storeTrackingCode(BusinessAffiliation $affiliation): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $code = SubmissionCode::generate();
            $hash = SubmissionCode::hash($code);
            $taken = BusinessAffiliation::query()->where('tracking_code_hash', $hash)->exists()
                || \App\Models\PublicRecord::query()->where('tracking_code_hash', $hash)->exists();

            if ($taken) {
                continue;
            }

            $affiliation->tracking_code_hash = $hash;
            $affiliation->save();

            return $code;
        }

        abort(503, 'Kode pelacakan belum bisa dibuat. Buat pengajuan sekali lagi.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ownershipPercentFromRequest(array $validated): ?float
    {
        if (($validated['type'] ?? '') !== BusinessAffiliation::TYPE_OWNERSHIP) {
            return null;
        }

        if (! empty($validated['ownership_unknown'])) {
            return null;
        }

        if (! array_key_exists('ownership_percent', $validated) || $validated['ownership_percent'] === null || $validated['ownership_percent'] === '') {
            return null;
        }

        return (float) $validated['ownership_percent'];
    }

    private function assertOwnershipPercent(Request $request, \Illuminate\Validation\Validator $validator): void
    {
        if ($request->input('type') !== BusinessAffiliation::TYPE_OWNERSHIP) {
            return;
        }

        $unknown = $request->boolean('ownership_unknown');
        $percent = $request->input('ownership_percent');

        if ($unknown) {
            return;
        }

        if ($percent === null || $percent === '') {
            // Unknown amount allowed when neither checkbox nor percent — treat empty as unknown.
            return;
        }

        if (! is_numeric($percent) || (float) $percent < 1 || (float) $percent > 100) {
            $validator->errors()->add('ownership_percent', 'Persentase kepemilikan harus antara 1 dan 100, atau pilih jumlah tidak diketahui.');
        }
    }

    private function businessFromChoice(?string $businessId, ?string $businessName): Business
    {
        if ($businessId === 'other' || $businessId === null || $businessId === '') {
            return Business::findOrCreateByName((string) $businessName, Business::STATUS_DRAFT);
        }

        return Business::query()->whereKey($businessId)->firstOrFail();
    }

    private function resolveBusinessIdForDuplicateCheck(Request $request): ?int
    {
        $businessId = (string) $request->input('business_id', '');

        if ($businessId === 'other') {
            $name = trim((string) $request->input('business_name', ''));
            if ($name === '') {
                return null;
            }

            return Business::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->value('id');
        }

        if ($businessId === '') {
            return null;
        }

        return Business::query()->whereKey($businessId)->value('id');
    }

    private function assertBusinessChoice(Request $request, \Illuminate\Validation\Validator $validator): void
    {
        $businessId = (string) $request->input('business_id', '');

        if ($businessId === '' || $businessId === 'other') {
            if ($businessId === 'other' && trim((string) $request->input('business_name', '')) === '') {
                $validator->errors()->add('business_name', 'Ketik nama bisnis jika memilih lainnya.');
            }

            if ($businessId === '') {
                $validator->errors()->add('business_id', 'Pilih bisnis dari daftar, atau ketik nama baru.');
            }

            return;
        }

        if (! Business::query()->whereKey($businessId)->exists()) {
            $validator->errors()->add('business_id', 'Pilih bisnis dari daftar, atau ketik nama baru.');
        }
    }

    private function resolvePartyId(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '' || in_array(mb_strtolower($name), ['independen', 'independent'], true)) {
            return null;
        }

        $party = Party::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

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
