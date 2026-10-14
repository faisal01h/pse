<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\SciPol\MaskedId;
use App\SciPol\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        return Inertia::render('SciPol/Isu', [
            'issues' => $workspace->issues(),
        ]);
    }

    public function show(string $issue, Workspace $workspace): Response
    {
        $id = $workspace->unmask($issue, MaskedId::TYPE_ISSUE);
        abort_unless($id, 404);
        $model = Issue::query()->findOrFail($id);
        $map = $workspace->issueMap($model);

        return Inertia::render('SciPol/IsuShow', [
            ...$map,
            'export_url' => route('scipol.export', [
                'scope' => 'issue',
                'issue' => $map['issue']['id'],
            ]),
        ]);
    }
}
