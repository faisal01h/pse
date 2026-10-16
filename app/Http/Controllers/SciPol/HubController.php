<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\SciPol\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class HubController extends Controller
{
    public function __invoke(Workspace $workspace, PluginManager $plugins): Response
    {
        $tools = [
            [
                'href' => route('scipol.compare'),
                'title' => 'Compare',
                'blurb' => 'Side-by-side matrix for 2–3 figures or parties: axes, issue stances, links, affiliations, and record counts.',
            ],
            [
                'href' => route('scipol.issues.index'),
                'title' => 'Issue map',
                'blurb' => 'Actor crosstabs by support / oppose / mixed on named issues.',
            ],
            [
                'href' => route('scipol.affiliations'),
                'title' => 'Affiliation network',
                'blurb' => 'Published ownership and endorsement ties, filterable by sector.',
            ],
            [
                'href' => route('scipol.topics'),
                'title' => 'Topic archive',
                'blurb' => 'Published records filtered by topic, category, and keyword.',
            ],
            [
                'href' => route('scipol.practice'),
                'title' => 'Source practice',
                'blurb' => 'Checklists for reading sources, then submitting or disputing data.',
            ],
        ];

        if ($plugins->isEnabled('realpolitik')) {
            array_splice($tools, 1, 0, [[
                'href' => route('scipol.realpolitik'),
                'title' => 'REALPOLITIK',
                'blurb' => 'Live power signals: coverage shocks, stance drift, coalition changes, cited sitreps, and as-of cui bono.',
            ]]);
        }

        return Inertia::render('SciPol/Index', [
            'codebook' => $workspace->codebook(),
            'tools' => $tools,
            'realpolitikActive' => $plugins->isEnabled('realpolitik'),
        ]);
    }
}
