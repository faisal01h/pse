<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Plugins\NewsRadar\Jobs\NewsRadarHuntJob;
use App\Plugins\PluginManager;

class NewsRadarController extends Controller
{
    public function __construct(private PluginManager $plugins) {}

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
