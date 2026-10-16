<?php

namespace App\Plugins\RealPolitik;

use App\Plugins\Plugin;

class RealPolitikPlugin implements Plugin
{
    public function id(): string
    {
        return 'realpolitik';
    }

    public function name(): string
    {
        return 'REALPOLITIK';
    }

    public function boot(): void
    {
        //
    }
}
