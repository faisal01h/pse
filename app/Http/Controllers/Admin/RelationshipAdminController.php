<?php

namespace App\Http\Controllers\Admin;

use App\Catalog\TemporalDuplicateException;
use App\Catalog\TemporalIntervalWriter;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use App\Models\IdeologyPosition;
use App\Models\Issue;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RelationshipAdminController extends Controller
{
    public function __construct(private TemporalIntervalWriter $intervals) {}

    public function suggestPoliticians(Request $request)
    {
        return response()->json($this->suggest(
            Politician::query()->orderBy('name'),
            $request,
            ['id', 'name', 'position', 'institution']
        ));
    }

    public function suggestParties(Request $request)
    {
        return response()->json($this->suggest(
            Party::query()->orderBy('name'),
            $request,
            ['id', 'name']
        ));
    }

    public function storePoliticianTendency(Request $request, Politician $politician): RedirectResponse
    {
        $validated = $this->validateTendency($request);
        $this->saveTendency($politician->id, null, $validated, $request);

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function destroyPoliticianTendency(Politician $politician, string $axis): RedirectResponse
    {
        $this->endTendency($politician->id, null, $axis);

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function storePartyTendency(Request $request, Party $party): RedirectResponse
    {
        $validated = $this->validateTendency($request);
        $this->saveTendency(null, $party->id, $validated, $request);

        return redirect()->route('admin.parties.edit', $party);
    }

    public function destroyPartyTendency(Party $party, string $axis): RedirectResponse
    {
        $this->endTendency(null, $party->id, $axis);

        return redirect()->route('admin.parties.edit', $party);
    }

    public function storePoliticianLink(Request $request, Politician $politician): RedirectResponse
    {
        $validated = $this->validateLink($request, PoliticianLink::typeKeys(), 'politicians', $politician->id);
        [$a, $b] = PoliticianLink::endpoints($validated['type'], $politician->id, (int) $validated['other_id']);

        try {
            $this->intervals->openPoliticianLink([
                'politician_a_id' => $a,
                'politician_b_id' => $b,
                'type' => $validated['type'],
                'summary' => trim($validated['summary']),
                ...$this->sourceColumns($validated, $request),
            ]);
        } catch (TemporalDuplicateException $e) {
            throw ValidationException::withMessages(['other_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function destroyPoliticianLink(Politician $politician, PoliticianLink $politicianLink): RedirectResponse
    {
        abort_unless(
            $politicianLink->politician_a_id === $politician->id || $politicianLink->politician_b_id === $politician->id,
            404
        );
        $this->intervals->endModel($politicianLink);

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function storePartyLink(Request $request, Party $party): RedirectResponse
    {
        $validated = $this->validateLink($request, PartyLink::typeKeys(), 'parties', $party->id);
        [$a, $b] = PartyLink::endpoints($validated['type'], $party->id, (int) $validated['other_id']);

        try {
            $this->intervals->openPartyLink([
                'party_a_id' => $a,
                'party_b_id' => $b,
                'type' => $validated['type'],
                'summary' => trim($validated['summary']),
                ...$this->sourceColumns($validated, $request),
            ]);
        } catch (TemporalDuplicateException $e) {
            throw ValidationException::withMessages(['other_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.parties.edit', $party);
    }

    public function destroyPartyLink(Party $party, PartyLink $partyLink): RedirectResponse
    {
        abort_unless(
            $partyLink->party_a_id === $party->id || $partyLink->party_b_id === $party->id,
            404
        );
        $this->intervals->endModel($partyLink);

        return redirect()->route('admin.parties.edit', $party);
    }

    public function storePoliticianStance(Request $request, Politician $politician): RedirectResponse
    {
        $validated = $this->validateStance($request);
        $issue = Issue::findOrCreateByName($validated['issue_name']);
        $this->saveStance($issue->id, $politician->id, null, $validated, $request);

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function destroyPoliticianStance(Politician $politician, IssueStance $issueStance): RedirectResponse
    {
        abort_unless($issueStance->politician_id === $politician->id, 404);
        $this->intervals->endModel($issueStance);

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function storePartyStance(Request $request, Party $party): RedirectResponse
    {
        $validated = $this->validateStance($request);
        $issue = Issue::findOrCreateByName($validated['issue_name']);
        $this->saveStance($issue->id, null, $party->id, $validated, $request);

        return redirect()->route('admin.parties.edit', $party);
    }

    public function destroyPartyStance(Party $party, IssueStance $issueStance): RedirectResponse
    {
        abort_unless($issueStance->party_id === $party->id, 404);
        $this->intervals->endModel($issueStance);

        return redirect()->route('admin.parties.edit', $party);
    }

    public function storeBusinessAffiliation(Request $request, Politician $politician): RedirectResponse
    {
        $this->blankDate($request);
        $request->merge([
            'ownership_percent' => $request->boolean('ownership_unknown')
                ? null
                : ($request->input('ownership_percent') !== '' ? $request->input('ownership_percent') : null),
        ]);

        $validated = $request->validate([
            'business_id' => 'nullable|string|max:20',
            'business_name' => 'nullable|string|max:255|required_if:business_id,other',
            'type' => ['required', Rule::in(BusinessAffiliation::typeKeys())],
            'ownership_percent' => 'nullable|numeric|min:1|max:100',
            'ownership_unknown' => 'nullable|boolean',
            ...$this->sourceRules('summary'),
        ], [
            'business_name.required_if' => 'Ketik nama bisnis jika memilih lainnya.',
            ...$this->sourceMessages('summary', 'Ringkasan'),
        ]);

        if (($validated['business_id'] ?? '') === 'other' || ($validated['business_id'] ?? '') === '') {
            if (trim((string) ($validated['business_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'business_id' => 'Pilih bisnis dari daftar, atau ketik nama baru.',
                ]);
            }
            $business = Business::findOrCreateByName($validated['business_name'], Business::STATUS_PUBLISHED);
        } else {
            $business = Business::query()->whereKey($validated['business_id'])->firstOrFail();
        }

        if ($validated['type'] !== BusinessAffiliation::TYPE_OWNERSHIP) {
            $validated['ownership_percent'] = null;
        } elseif ($request->boolean('ownership_unknown')) {
            $validated['ownership_percent'] = null;
        }

        $this->rejectDuplicate(
            BusinessAffiliation::query()
                ->where('politician_id', $politician->id)
                ->where('business_id', $business->id)
                ->where('type', $validated['type'])
                ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
                ->exists(),
            'business_id',
            'Afiliasi ini sudah tercatat.'
        );

        $business->status = Business::STATUS_PUBLISHED;
        $business->save();

        BusinessAffiliation::create([
            'politician_id' => $politician->id,
            'business_id' => $business->id,
            'type' => $validated['type'],
            'ownership_percent' => $validated['ownership_percent'] ?? null,
            'summary' => trim($validated['summary']),
            'status' => BusinessAffiliation::STATUS_PUBLISHED,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'check_source' => true,
            'check_wording' => true,
            'check_category' => true,
            'check_duplicate' => true,
            'check_legal_label' => true,
            'review_note' => 'Ditambahkan langsung oleh pemeriksa.',
            ...$this->sourceColumns($validated, $request),
        ]);

        if ((int) $politician->status !== 1) {
            $politician->status = 1;
            $politician->save();
        }

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function destroyBusinessAffiliation(Politician $politician, BusinessAffiliation $businessAffiliation): RedirectResponse
    {
        abort_unless($businessAffiliation->politician_id === $politician->id, 404);
        $businessAffiliation->delete();

        return redirect()->route('admin.politicians.edit', $politician);
    }

    private function suggest($query, Request $request, array $columns)
    {
        $term = trim((string) $request->query('q', ''));
        $except = $request->integer('except');

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return [];
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query
            ->where('name', 'like', $like)
            ->when($except > 0, fn ($builder) => $builder->where('id', '!=', $except))
            ->limit(8)
            ->get($columns);
    }

    private function validateTendency(Request $request): array
    {
        $this->blankDate($request);

        return $request->validate([
            'axis' => ['required', Rule::in(IdeologyPosition::axisKeys())],
            'score' => ['required', 'integer', 'between:0,100'],
            ...$this->sourceRules('note'),
        ], $this->sourceMessages('note', 'Catatan sumber'));
    }

    private function validateLink(Request $request, array $types, string $table, int $selfId): array
    {
        $this->blankDate($request);

        return $request->validate([
            'other_id' => ['required', 'integer', 'exists:'.$table.',id', Rule::notIn([$selfId])],
            'type' => ['required', Rule::in($types)],
            ...$this->sourceRules('summary'),
        ], [
            'other_id.required' => 'Pilih nama dari daftar.',
            'other_id.exists' => 'Pilih nama dari daftar.',
            'other_id.not_in' => 'Hubungan tidak bisa menuju ke profil yang sama.',
            ...$this->sourceMessages('summary', 'Ringkasan'),
        ]);
    }

    private function validateStance(Request $request): array
    {
        $this->blankDate($request);

        return $request->validate([
            'issue_name' => 'required|string|max:255',
            'position' => ['required', Rule::in(IssueStance::positionKeys())],
            ...$this->sourceRules('note'),
        ], [
            'issue_name.required' => 'Nama isu wajib diisi.',
            ...$this->sourceMessages('note', 'Catatan sumber'),
        ]);
    }

    private function sourceRules(string $textKey): array
    {
        return [
            $textKey => 'required|string|min:40|max:5000',
            'source_url' => ['required', 'string', 'max:2048', 'regex:/^https:\/\/\S+$/i'],
            'source_type' => 'required|string|max:100',
            'occurred_on' => 'nullable|date',
        ];
    }

    private function sourceMessages(string $textKey, string $label): array
    {
        return [
            $textKey.'.required' => $label.' wajib diisi.',
            $textKey.'.min' => $label.' minimal 40 karakter.',
            'source_url.required' => 'Tautan sumber wajib diisi.',
            'source_url.regex' => 'Tautan sumber harus berupa https.',
            'source_type.required' => 'Jenis sumber wajib diisi.',
        ];
    }

    private function sourceColumns(array $validated, Request $request): array
    {
        return [
            'source_url' => $validated['source_url'],
            'source_type' => trim($validated['source_type']),
            'occurred_on' => $validated['occurred_on'] ?? null,
            'user_id' => $request->user()->id,
        ];
    }

    private function blankDate(Request $request): void
    {
        $request->merge([
            'occurred_on' => $request->input('occurred_on') ?: null,
        ]);
    }

    private function saveTendency(?int $politicianId, ?int $partyId, array $validated, Request $request): void
    {
        $this->intervals->supersedeTendency([
            'politician_id' => $politicianId,
            'party_id' => $partyId,
            'axis' => $validated['axis'],
            'score' => (int) $validated['score'],
            'samples' => 1,
            'note' => trim($validated['note']),
            ...$this->sourceColumns($validated, $request),
        ]);
    }

    private function endTendency(?int $politicianId, ?int $partyId, string $axis): void
    {
        abort_unless(in_array($axis, IdeologyPosition::axisKeys(), true), 404);
        $this->intervals->endOpenTendencies($politicianId, $partyId, $axis);
    }

    private function saveStance(int $issueId, ?int $politicianId, ?int $partyId, array $validated, Request $request): void
    {
        $this->intervals->supersedeStance([
            'issue_id' => $issueId,
            'politician_id' => $politicianId,
            'party_id' => $partyId,
            'position' => $validated['position'],
            'note' => trim($validated['note']),
            ...$this->sourceColumns($validated, $request),
        ]);
    }

    private function rejectDuplicate(bool $exists, string $field = 'other_id', string $message = 'Hubungan ini sudah tercatat.'): void
    {
        if ($exists) {
            throw ValidationException::withMessages([
                $field => $message,
            ]);
        }
    }
}
