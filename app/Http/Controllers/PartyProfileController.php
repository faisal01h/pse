<?php

namespace App\Http\Controllers;

use App\Catalog\PageMeta;
use App\Catalog\RelationshipGraph;
use App\Models\Party;

class PartyProfileController extends Controller
{
    public function __construct(private RelationshipGraph $graph) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, meta: array{current: int, last: int, prev: ?int, next: ?int}}
     */
    public function catalog(?string $term = null): array
    {
        $term = trim((string) $term);
        $like = $term === '' ? null : '%'.addcslashes($term, '%_\\').'%';

        $page = Party::query()
            ->when($like, fn ($query) => $query->where('name', 'like', $like))
            ->withCount(['politicians as published_politicians_count' => function ($query) {
                $query->where('status', 1);
            }])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) config('catalog.parties_per_page'));

        return [
            'items' => $page->getCollection()->map(fn (Party $party) => [
                'id' => $party->id,
                'name' => $party->name,
                'logo_url' => $party->logo_url,
                'published_politicians_count' => (int) $party->published_politicians_count,
                'href' => route('parties.show', $party),
            ])->values()->all(),
            'meta' => PageMeta::from($page),
        ];
    }

    public function show(Party $party): Party
    {
        return $this->graph->presentParty($party);
    }
}
