<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Pages\PageHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages', [
            'pages' => Page::query()->orderByDesc('pinned')->orderBy('title')->get(['id', 'title', 'slug', 'format', 'pinned', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PageForm', [
            'page' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePage($request);

        $page = Page::create([
            'title' => trim($validated['title']),
            'slug' => $validated['slug'],
            'format' => $validated['format'],
            'body' => $this->body($validated['format'], $validated['body'] ?? ''),
            'pinned' => false,
        ]);

        return redirect()->route('admin.pages.edit', $page);
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('Admin/PageForm', [
            'page' => $page->only(['id', 'title', 'slug', 'format', 'body', 'pinned']),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $this->validatePage($request, $page);
        $page->fill([
            'title' => trim($validated['title']),
            'slug' => $page->pinned ? $page->slug : $validated['slug'],
            'format' => $validated['format'],
            'body' => $this->body($validated['format'], $validated['body'] ?? ''),
        ]);
        $page->save();

        return redirect()->route('admin.pages.edit', $page);
    }

    public function destroy(Page $page): RedirectResponse
    {
        if ($page->pinned) {
            return redirect()->route('admin.pages.edit', $page)->withErrors([
                'slug' => 'Halaman ini tidak bisa dihapus.',
            ]);
        }

        $page->delete();

        return redirect()->route('admin.pages.index');
    }

    /**
     * @return array{title: string, slug: string, format: string, body: ?string}
     */
    private function validatePage(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:160',
            'slug' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($page),
            ],
            'format' => ['required', Rule::in(Page::FORMATS)],
            'body' => 'nullable|string|max:200000',
        ], [
            'title.required' => 'Judul wajib diisi.',
            'slug.required' => 'Alamat halaman wajib diisi.',
            'slug.regex' => 'Alamat halaman hanya boleh huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'Alamat halaman itu sudah dipakai.',
        ]);
    }

    private function body(string $format, string $body): string
    {
        if ($format === Page::FORMAT_RICHTEXT) {
            return PageHtml::sanitize($body);
        }

        return str_replace("\0", '', $body);
    }
}
