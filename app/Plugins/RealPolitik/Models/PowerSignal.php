<?php

namespace App\Plugins\RealPolitik\Models;

use App\Models\Party;
use App\Models\Politician;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerSignal extends Model
{
    protected $table = 'realpolitik_signals';

    public const TYPE_SHOCK = 'shock';

    public const TYPE_ALLEGATION_SPIKE = 'allegation_spike';

    public const TYPE_DRIFT = 'drift';

    public const TYPE_COALITION_CHANGE = 'coalition_change';

    public const TYPE_SITREP = 'sitrep';

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    protected $fillable = [
        'type',
        'severity',
        'politician_id',
        'party_id',
        'topic_id',
        'brief_id',
        'title',
        'body',
        'evidence',
        'dismissed_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'dismissed_at' => 'datetime',
    ];

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(SituationBrief::class, 'brief_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at');
    }
}
