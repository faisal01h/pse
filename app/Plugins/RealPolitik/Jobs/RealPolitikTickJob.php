<?php

namespace App\Plugins\RealPolitik\Jobs;

use App\Plugins\PluginManager;
use App\Plugins\RealPolitik\RealPolitikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RealPolitikTickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public bool $force = false) {}

    public function handle(PluginManager $plugins, RealPolitikService $service): void
    {
        if (! $plugins->isEnabled('realpolitik')) {
            return;
        }

        $service->tick($this->force);
    }
}
