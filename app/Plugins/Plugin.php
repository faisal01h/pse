<?php

namespace App\Plugins;

interface Plugin
{
    public function id(): string;

    public function name(): string;

    public function boot(): void;
}
