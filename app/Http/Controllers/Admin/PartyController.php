<?php

namespace App\Http\Controllers\Admin;

use App\Catalog\RelationshipGraph;
use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Parties', [
            'parties' => Party::query()
                ->withCount('politicians')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PartyForm', [
            'party' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateParty($request);

        $party = Party::create([
            'name' => trim($validated['name']),
            'logo_path' => $this->storeLogo($request),
        ]);

        return redirect()->route('admin.parties.edit', $party);
    }

    public function edit(Party $party): Response
    {
        return Inertia::render('Admin/PartyForm', [
            'party' => $party,
            'graph' => app(RelationshipGraph::class)->adminParty($party),
        ]);
    }

    public function update(Request $request, Party $party): RedirectResponse
    {
        $validated = $this->validateParty($request, $party);
        $party->name = trim($validated['name']);

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            $this->deleteLogo($party->logo_path);
            $party->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            $party->logo_path = $this->storeLogo($request);
        }

        $party->save();

        return redirect()->route('admin.parties.edit', $party);
    }

    public function destroy(Party $party): RedirectResponse
    {
        if ($party->politicians()->exists()) {
            return redirect()->route('admin.parties.edit', $party)->withErrors([
                'name' => 'Partai masih dipakai profil politisi. Pindahkan atau hapus profil itu lebih dulu.',
            ]);
        }

        $this->deleteLogo($party->logo_path);
        $party->delete();

        return redirect()->route('admin.parties.index');
    }

    private function validateParty(Request $request, ?Party $party = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|file|max:2048|mimetypes:image/jpeg,image/png,image/webp,image/gif',
            'remove_logo' => 'nullable|boolean',
        ], [
            'name.required' => 'Nama partai wajib diisi.',
            'logo.max' => 'Logo maksimal 2 MB.',
            'logo.mimetypes' => 'Logo harus berupa JPEG, PNG, WebP, atau GIF.',
        ]);

        $existing = app(DuplicateDetector::class)->firstExact(NameNormalizer::PARTY, $validated['name'], $party?->id);

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Nama partai itu sudah ada ("'.$existing->name.'"). Gunakan data yang ada atau gabungkan lewat menu Data ganda.',
            ]);
        }

        return $validated;
    }

    private function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        return $request->file('logo')->store('parties', 'public');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
