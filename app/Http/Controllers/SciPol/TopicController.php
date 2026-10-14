<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): Response
    {
        $data = $workspace->topicArchive($request);

        $exportQuery = array_filter([
            'scope' => 'topics',
            'topic' => $data['filters']['topic'] ?: null,
            'category' => $data['filters']['category'] ?: null,
            'q' => $data['filters']['q'] ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        return Inertia::render('SciPol/Topik', [
            ...$data,
            'export_url' => route('scipol.export', $exportQuery),
        ]);
    }
}
