<?php

namespace App\Http\Controllers\Admin;

use App\Catalog\RelationshipGraph;
use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Http\Controllers\Controller;
use App\Inference\ProposalQueue;
use App\Models\Party;
use App\Models\Politician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PoliticianAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $status = in_array($status, ['0', '1'], true) ? $status : '';

        $matching = Politician::query()
            ->when($term !== '', function ($query) use ($term) {
                $query->where('name', 'like', '%'.$term.'%');
            });

        $politicians = (clone $matching)
            ->with('party')
            ->withCount('records')
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', (int) $status);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Politicians', [
            'politicians' => $politicians,
            'filters' => ['q' => $term, 'status' => $status],
            'counts' => [
                'all' => (clone $matching)->count(),
                'published' => (clone $matching)->where('status', 1)->count(),
                'hidden' => (clone $matching)->where('status', 0)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PoliticianForm', [
            'politician' => null,
            'parties' => $this->parties(),
            'records' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePolitician($request);
        $this->guardDuplicateName($request, $validated['name']);

        $politician = Politician::create([
            'name' => trim($validated['name']),
            'position' => $validated['position'],
            'institution' => $validated['institution'],
            'party_id' => $validated['party_id'] ?: null,
            'status' => (int) $validated['status'],
            'picture_url' => $this->storePhoto($request),
        ]);

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function edit(Politician $politician): Response
    {
        $politician->load('party');

        return Inertia::render('Admin/PoliticianForm', [
            'politician' => $politician,
            'parties' => $this->parties(),
            'records' => $politician->records()
                ->latest()
                ->get(['id', 'category', 'status', 'quote', 'summary', 'created_at']),
            'graph' => app(RelationshipGraph::class)->adminPolitician($politician),
            'proposals' => app(ProposalQueue::class)->pendingForPolitician($politician->id),
        ]);
    }

    public function update(Request $request, Politician $politician): RedirectResponse
    {
        $validated = $this->validatePolitician($request);
        $this->guardDuplicateName($request, $validated['name'], $politician);
        $politician->fill([
            'name' => trim($validated['name']),
            'position' => $validated['position'],
            'institution' => $validated['institution'],
            'party_id' => $validated['party_id'] ?: null,
            'status' => (int) $validated['status'],
        ]);

        if ($request->boolean('remove_photo') || $request->hasFile('photo')) {
            $this->deletePhoto($politician->picture_url);
            $politician->picture_url = null;
        }

        if ($request->hasFile('photo')) {
            $politician->picture_url = $this->storePhoto($request);
        }

        $politician->save();

        return redirect()->route('admin.politicians.edit', $politician);
    }

    public function destroy(Politician $politician): RedirectResponse
    {
        $politician->load('records.attachments');

        foreach ($politician->records as $record) {
            foreach ($record->attachments as $attachment) {
                $attachment->deleteFile();
            }
        }

        $this->deletePhoto($politician->picture_url);
        $politician->delete();

        return redirect()->route('admin.politicians.index');
    }

    /**
     * Same-name people exist, so a normalized-name match warns and needs explicit confirmation.
     */
    private function guardDuplicateName(Request $request, string $name, ?Politician $current = null): void
    {
        if ($request->boolean('confirm_duplicate')) {
            return;
        }

        if ($current && app(NameNormalizer::class)->normalize(NameNormalizer::POLITICIAN, $current->name)
            === app(NameNormalizer::class)->normalize(NameNormalizer::POLITICIAN, $name)) {
            return;
        }

        $matches = app(DuplicateDetector::class)->exactMatches(NameNormalizer::POLITICIAN, $name, $current?->id);

        if ($matches->isEmpty()) {
            return;
        }

        $list = $matches->take(3)->map(fn (Politician $match) => $match->name.' ('.$match->institution.')')->implode('; ');

        throw ValidationException::withMessages([
            'name' => 'Nama mirip dengan profil yang sudah ada: '.$list.'. Centang "Tetap simpan sebagai orang berbeda" jika memang berbeda.',
        ]);
    }

    private function parties()
    {
        return Party::query()->orderBy('name')->get(['id', 'name']);
    }

    private function validatePolitician(Request $request): array
    {
        $request->merge([
            'party_id' => $request->input('party_id') ?: null,
        ]);

        return $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'institution' => 'required|string|max:255',
            'party_id' => 'nullable|exists:parties,id',
            'status' => 'required|in:0,1',
            'photo' => 'nullable|file|max:2048|mimetypes:image/jpeg,image/png,image/webp,image/gif',
            'remove_photo' => 'nullable|boolean',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'position.required' => 'Jabatan wajib diisi.',
            'institution.required' => 'Instansi wajib diisi.',
            'photo.max' => 'Foto maksimal 2 MB.',
            'photo.mimetypes' => 'Foto harus berupa JPEG, PNG, WebP, atau GIF.',
            'party_id.exists' => 'Pilih partai dari daftar.',
        ]);
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $request->file('photo')->store('politicians', 'public');
    }

    private function deletePhoto(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
