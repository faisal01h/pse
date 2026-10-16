<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRun extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const FINISHED = 'finished';

    public const FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'name',
        'connection',
        'queue',
        'status',
        'attempts',
        'max_tries',
        'timeout',
        'summary',
        'exception',
        'queued_at',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected $casts = [
        'summary' => 'array',
        'attempts' => 'integer',
        'max_tries' => 'integer',
        'timeout' => 'integer',
        'duration_ms' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
