<?php

namespace App\Plugins\NewsRadar\Jobs;

use App\Plugins\NewsRadar\Models\NewsRadarSighting;
use App\Plugins\NewsRadar\NewsRadarHunter;
use App\Plugins\PluginManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NewsRadarItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $sightingId) {}

    public function handle(PluginManager $plugins, NewsRadarHunter $hunter): void
    {
        if (! $plugins->isEnabled('newsradar')) {
            return;
        }

        $sighting = NewsRadarSighting::query()->find($this->sightingId);
        if (! $sighting || $sighting->status !== NewsRadarSighting::STATUS_PENDING) {
            return;
        }

        $hunter->process($sighting);
    }
}
