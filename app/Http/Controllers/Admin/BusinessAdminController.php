<?php

namespace App\Http\Controllers\Admin;

use App\Duplicates\DuplicateDetector;
use App\Duplicates\NameNormalizer;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BusinessAdminController extends Controller
{
    public function index(): Response
    {
        $businesses = Business::query()
            ->withCount('affiliations')
            ->orderBy('name')
            ->get()
            ->map(fn (Business $business) => [
                'id' => $business->id,
                'name' => $business->name,
                'sector' => $business->sector,
                'status' => (int) $business->status,
                'affiliations_count' => $business->affiliations_count,
            ]);

        return Inertia::render('Admin/Businesses', [
            'businesses' => $businesses,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/BusinessForm', [
            'business' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBusiness($request);

        $business = Business::create([
            'name' => trim($validated['name']),
            'sector' => $this->nullableTrim($validated['sector'] ?? null),
            'status' => (int) $validated['status'],
        ]);

        return redirect()->route('admin.businesses.edit', $business);
    }

    public function edit(Business $business): Response
    {
        $business->loadCount('affiliations');

        return Inertia::render('Admin/BusinessForm', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'sector' => $business->sector,
                'status' => (int) $business->status,
                'affiliations_count' => $business->affiliations_count,
            ],
        ]);
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $this->validateBusiness($request, $business);

        $business->fill([
            'name' => trim($validated['name']),
            'sector' => $this->nullableTrim($validated['sector'] ?? null),
            'status' => (int) $validated['status'],
        ]);
        $business->save();

        return redirect()->route('admin.businesses.edit', $business);
    }

    public function destroy(Business $business): RedirectResponse
    {
        if ($business->affiliations()->exists()) {
            return redirect()->route('admin.businesses.edit', $business)->withErrors([
                'name' => 'Bisnis masih punya afiliasi. Hapus atau pindahkan afiliasi itu lebih dulu.',
            ]);
        }

        $business->delete();

        return redirect()->route('admin.businesses.index');
    }

    /**
     * @return array{name: string, sector?: ?string, status: int|string}
     */
    private function validateBusiness(Request $request, ?Business $business = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'status' => ['required', Rule::in([Business::STATUS_DRAFT, Business::STATUS_PUBLISHED])],
        ], [
            'name.required' => 'Nama bisnis wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ]);

        $existing = app(DuplicateDetector::class)->firstExact(NameNormalizer::BUSINESS, $validated['name'], $business?->id);

        if ($existing) {
            throw ValidationException::withMessages([
                'name' => 'Nama bisnis itu sudah ada ("'.$existing->name.'"). Gunakan data yang ada atau gabungkan lewat menu Data ganda.',
            ]);
        }

        return $validated;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
