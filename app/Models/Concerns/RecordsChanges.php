<?php

namespace App\Models\Concerns;

use App\Auditing\ChangeLog;

trait RecordsChanges
{
    public static function bootRecordsChanges(): void
    {
        $log = fn () => app(ChangeLog::class);

        static::created(fn ($model) => $log()->created($model));
        static::updated(fn ($model) => $log()->updated($model));
        static::deleted(fn ($model) => $log()->deleted($model));
    }
}
