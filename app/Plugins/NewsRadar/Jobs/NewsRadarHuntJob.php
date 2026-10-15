<?php

namespace App\Plugins\NewsRadar\Jobs;

use App\Plugins\NewsRadar\NewsRadarService;
use App\Plugins\PluginManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NewsRadarHuntJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public bool $force = false) {}

    public function handle(PluginManager $plugins, NewsRadarService $service): void
    {
        if (! $plugins->isEnabled('newsradar')) {
            return;
        }

        $service->hunt(force: $this->force);
    }
}
