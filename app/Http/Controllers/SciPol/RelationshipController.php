<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\MaskedId;
use App\SciPol\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RelationshipController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $type = $request->query('type') === MaskedId::TYPE_PARTY
            ? MaskedId::TYPE_PARTY
            : MaskedId::TYPE_POLITICIAN;
        $asOf = $request->query('as_of');
        if (! is_string($asOf) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
            $asOf = null;
        }
        $id = $request->query('id');

        return Inertia::render('SciPol/Relationships', $workspace->relationshipBrowser(
            $type,
            is_string($id) ? $id : null,
            $asOf,
            $request->boolean('history'),
        ));
    }
}
