<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_INFERENCE = 'inference';

    public const ACTION_MERGED = 'merged';

    public const TRIGGER_PUBLISH = 'publish';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_DATA = 'data';

    public const STATUS_RAN = 'ran';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RECORDED = 'recorded';

    protected $fillable = [
        'user_id',
        'action',
        'subject',
        'auditable_type',
        'auditable_id',
        'public_record_id',
        'trigger',
        'method',
        'status',
        'proposals_created',
        'message',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(PublicRecord::class, 'public_record_id');
    }
}
