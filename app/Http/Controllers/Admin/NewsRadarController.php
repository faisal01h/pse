<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\NewsRadar\HeadlessArticleReader;
use App\Plugins\NewsRadar\Jobs\NewsRadarHuntJob;
use App\Plugins\PluginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NewsRadarController extends Controller
{
    public function __construct(private PluginManager $plugins) {}

    public function browserTest(Request $request, HeadlessArticleReader $reader): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url:http,https|max:2048',
        ]);

        try {
            return response()->json(['ok' => true] + $reader->probe($validated['url']));
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function hunt()
    {
        if (! $this->plugins->isEnabled('newsradar')) {
            return back()->withErrors([
                'newsradar' => 'NewsRadar belum diaktifkan atau LLM belum siap.',
            ]);
        }

        NewsRadarHuntJob::dispatch(force: true);

        return back()->with('success', 'Pencarian NewsRadar dimasukkan antrean.');
    }
}
