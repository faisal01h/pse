<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\MaskedId;
use App\SciPol\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompareController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $type = $request->query('type') === 'party' ? MaskedId::TYPE_PARTY : MaskedId::TYPE_POLITICIAN;
        $tokens = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('ids', ''))
        )));
        $ids = $workspace->resolveTokens($tokens, $type, 3);

        $subjects = $type === MaskedId::TYPE_PARTY
            ? $workspace->compareParties($ids)
            : $workspace->comparePoliticians($ids);

        $maskedIds = array_map(fn (array $subject) => $subject['id'], $subjects);

        return Inertia::render('SciPol/Compare', [
            'type' => $type,
            'ids' => $maskedIds,
            'subjects' => $subjects,
            'export_url' => count($maskedIds) > 0
                ? route('scipol.export', ['scope' => 'compare', 'type' => $type, 'ids' => implode(',', $maskedIds)])
                : null,
        ]);
    }
}
