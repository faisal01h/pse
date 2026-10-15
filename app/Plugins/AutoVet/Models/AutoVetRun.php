<?php

namespace App\Plugins\AutoVet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AutoVetRun extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'autovet_runs';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'status',
        'mode',
        'result',
        'error',
        'steps',
        'tokens',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'steps' => 'integer',
        'tokens' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
