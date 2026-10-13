<?php

namespace App\Http\Controllers;

use App\Search\CatalogSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(Request $request, CatalogSearch $search): JsonResponse|Response
    {
        $query = trim((string) $request->query('q', ''));
        $results = $search->search($query);

        if ($request->wantsJson()) {
            return response()->json([
                'query' => $query,
                'results' => $results,
            ]);
        }

        return Inertia::render('Search', [
            'query' => $query,
            'results' => $results,
        ]);
    }
}
