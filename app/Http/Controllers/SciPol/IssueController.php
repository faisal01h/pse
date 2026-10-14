<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\Models\Issue;
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

    public function show(Issue $issue, Workspace $workspace): Response
    {
        $map = $workspace->issueMap($issue);

        return Inertia::render('SciPol/IsuShow', [
            ...$map,
            'export_url' => route('scipol.export', ['scope' => 'issue', 'issue' => $issue->id]),
        ]);
    }
}
