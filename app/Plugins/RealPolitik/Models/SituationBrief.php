<?php

namespace App\Plugins\RealPolitik\Models;

use App\Models\PublicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SituationBrief extends Model
{
    protected $table = 'realpolitik_briefs';

    public const STATUS_READY = 'ready';

    public const STATUS_LOW_CONFIDENCE = 'low_confidence';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'window_start',
        'window_end',
        'summary_md',
        'payload',
        'status',
        'confidence',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'payload' => 'array',
    ];

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(
            PublicRecord::class,
            'realpolitik_brief_public_record',
            'situation_brief_id',
            'public_record_id'
        );
    }

    public function signals(): HasMany
    {
        return $this->hasMany(PowerSignal::class, 'brief_id');
    }
}
