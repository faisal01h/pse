<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelationshipProposal extends Model
{
    use RecordsChanges;

    public const KIND_AXIS = 'axis';

    public const KIND_STANCE = 'stance';

    public const KIND_LINK = 'politician_link';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'public_record_id',
        'kind',
        'fingerprint',
        'payload',
        'excerpt',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'decided_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(PublicRecord::class, 'public_record_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public static function fingerprint(string $kind, array $payload): string
    {
        return match ($kind) {
            self::KIND_AXIS => 'axis:'.($payload['axis'] ?? ''),
            self::KIND_STANCE => 'stance:'.mb_strtolower(trim((string) ($payload['issue_name'] ?? ''))),
            self::KIND_LINK => 'link:'.($payload['other_politician_id'] ?? '').':'.($payload['type'] ?? ''),
            default => $kind,
        };
    }
}
