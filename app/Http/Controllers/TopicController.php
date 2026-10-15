<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function show(Topic $topic, PoliticianController $politicians): Response
    {
        $reports = $politicians->publishedReports(topicId: $topic->id);

        return Inertia::render('Topic', [
            'topic' => $topic->only(['id', 'name']),
            'reports' => $reports['items'],
            'meta' => $reports['meta'],
            'total' => $reports['total'],
        ]);
    }
}
