<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompareController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $type = $request->query('type') === 'party' ? 'party' : 'politician';
        $ids = $workspace->normalizeIds(explode(',', (string) $request->query('ids', '')), 3);

        $subjects = $type === 'party'
            ? $workspace->compareParties($ids)
            : $workspace->comparePoliticians($ids);

        return Inertia::render('SciPol/Bandingkan', [
            'type' => $type,
            'ids' => $ids,
            'subjects' => $subjects,
            'export_url' => count($ids) > 0
                ? route('scipol.export', ['scope' => 'compare', 'type' => $type, 'ids' => implode(',', $ids)])
                : null,
        ]);
    }
}
