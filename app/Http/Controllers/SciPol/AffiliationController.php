<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AffiliationController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $data = $workspace->affiliations($request);

        $exportQuery = array_filter([
            'scope' => 'affiliations',
            'sector' => $data['filters']['sector'] ?: null,
            'type' => $data['filters']['type'] ?: null,
            'politician' => $data['filters']['politician'] ?: null,
            'q' => $data['filters']['q'] ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        return Inertia::render('SciPol/Affiliations', [
            ...$data,
            'export_url' => route('scipol.export', $exportQuery),
        ]);
    }
}
