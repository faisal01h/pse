<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\NewsRadar\HeadlessArticleReader;
use App\Plugins\NewsRadar\Jobs\NewsRadarHuntJob;
use App\Plugins\NewsRadar\PlaywrightNotConfiguredException;
use App\Plugins\NewsRadar\PlaywrightSetup;
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
        } catch (PlaywrightNotConfiguredException $e) {
            return response()->json(['ok' => false, 'needs_setup' => true, 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function playwrightStatus(PlaywrightSetup $setup): JsonResponse
    {
        return response()->json(['ok' => true] + $setup->status());
    }

    public function configurePlaywright(PlaywrightSetup $setup): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + $setup->configure());
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
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
