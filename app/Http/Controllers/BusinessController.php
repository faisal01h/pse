<?php

namespace App\Http\Controllers;

use App\Catalog\PageMeta;
use App\Models\Business;
use App\Models\BusinessAffiliation;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}}
     */
    public function catalog(?string $term = null): array
    {
        $term = trim((string) $term);
        $like = $term === '' ? null : '%'.addcslashes($term, '%_\\').'%';

        $page = Business::query()
            ->where('status', Business::STATUS_PUBLISHED)
            ->whereHas('affiliations', function ($query) {
                $query->where('status', BusinessAffiliation::STATUS_PUBLISHED)
                    ->whereHas('politician', fn ($q) => $q->where('status', 1));
            })
            ->when($like, fn ($query) => $query->where('name', 'like', $like))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) config('catalog.businesses_per_page', 24));

        return [
            'items' => $page->getCollection()->map(fn (Business $business) => [
                'id' => $business->id,
                'name' => $business->name,
                'sector' => $business->sector,
                'href' => route('businesses.show', $business),
            ])->values()->all(),
            'meta' => PageMeta::from($page),
        ];
    }

    public function show(Business $business): array
    {
        abort_unless((int) $business->status === Business::STATUS_PUBLISHED, 404);

        $affiliations = BusinessAffiliation::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessAffiliation::STATUS_PUBLISHED)
            ->whereHas('politician', fn ($q) => $q->where('status', 1))
            ->with(['politician:id,name,status,position,institution,picture_url', 'attachments'])
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn (BusinessAffiliation $affiliation) => [
                'id' => $affiliation->id,
                'type' => $affiliation->type,
                'label' => $affiliation->typeLabel(),
                'ownership_percent' => $affiliation->ownership_percent,
                'summary' => $affiliation->summary,
                'source_url' => $affiliation->source_url,
                'source_type' => $affiliation->source_type,
                'reliability_level' => $affiliation->reliability_level,
                'occurred_on' => optional($affiliation->occurred_on)->toDateString(),
                'attachments' => $affiliation->attachments->map(fn ($file) => [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'url' => $file->url,
                    'kind' => $file->kind,
                ])->values(),
                'politician' => [
                    'id' => $affiliation->politician->id,
                    'name' => $affiliation->politician->name,
                    'photo_url' => $affiliation->politician->photo_url,
                    'position' => $affiliation->politician->position,
                    'institution' => $affiliation->politician->institution,
                    'href' => route('politicians.show', $affiliation->politician_id),
                ],
            ])
            ->values()
            ->all();

        abort_if($affiliations === [], 404);

        return [
            'id' => $business->id,
            'name' => $business->name,
            'sector' => $business->sector,
            'affiliations' => $affiliations,
        ];
    }

    public function suggest(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return response()->json([]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $matches = Business::query()
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sector']);

        return response()->json($matches->map(fn (Business $business) => [
            'id' => $business->id,
            'name' => $business->name,
            'sector' => $business->sector,
        ])->values());
    }
}
