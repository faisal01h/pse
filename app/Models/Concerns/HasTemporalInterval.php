<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

trait HasTemporalInterval
{
    public function initializeHasTemporalInterval(): void
    {
        $this->mergeFillable(['effective_from', 'ended_at']);
        $this->mergeCasts([
            'effective_from' => 'date',
            'ended_at' => 'date',
        ]);
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->getTable().'.ended_at');
    }

    public function scopeAsOf(Builder $query, CarbonInterface|string $date): Builder
    {
        $day = $date instanceof CarbonInterface
            ? $date->toDateString()
            : (string) $date;

        $table = $query->getModel()->getTable();

        return $query
            ->where($table.'.effective_from', '<=', $day)
            ->where(function (Builder $inner) use ($table, $day) {
                $inner->whereNull($table.'.ended_at')
                    ->orWhere($table.'.ended_at', '>=', $day);
            });
    }

    public function scopeCurrentPreferred(Builder $query): Builder
    {
        return $query->open()
            ->orderByDesc($query->getModel()->getTable().'.effective_from')
            ->orderByDesc($query->getModel()->getTable().'.id');
    }
}
