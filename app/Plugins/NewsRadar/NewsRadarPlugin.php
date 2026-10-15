<?php

namespace App\Plugins\NewsRadar;

use App\Plugins\Plugin;

class NewsRadarPlugin implements Plugin
{
    public function id(): string
    {
        return 'newsradar';
    }

    public function name(): string
    {
        return 'NewsRadar';
    }

    public function boot(): void
    {
        //
    }
}
