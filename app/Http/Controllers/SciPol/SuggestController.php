<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Politician;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $type = $request->query('type') === 'party' ? 'party' : 'politician';

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80) {
            return response()->json([]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        if ($type === 'party') {
            $rows = Party::query()
                ->where('name', 'like', $like)
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name']);

            return response()->json($rows->map(fn (Party $party) => [
                'id' => $party->id,
                'name' => $party->name,
            ])->values());
        }

        $rows = Politician::query()
            ->where('status', 1)
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'position', 'institution']);

        return response()->json($rows->map(fn (Politician $politician) => [
            'id' => $politician->id,
            'name' => $politician->name,
            'position' => $politician->position,
            'institution' => $politician->institution,
        ])->values());
    }
}
