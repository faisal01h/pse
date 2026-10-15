<?php

namespace App\Plugins\AutoVet;

use App\Plugins\Plugin;

class AutoVetPlugin implements Plugin
{
    public function id(): string
    {
        return 'autovet';
    }

    public function name(): string
    {
        return 'AutoVet';
    }

    public function boot(): void
    {
        $cwd = storage_path('app/autovet');
        if (! is_dir($cwd)) {
            @mkdir($cwd, 0755, true);
        }
    }
}
