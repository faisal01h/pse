<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use App\Tracking\SubmissionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    use RecordsChanges;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UPHELD = 'upheld';

    public const STATUS_DISMISSED = 'dismissed';

    public const TARGET_RECORD = 'record';

    public const TARGET_AFFILIATION = 'affiliation';

    protected static function booted(): void
    {
        static::creating(function (Dispute $dispute) {
            if (! $dispute->tracking_code) {
                $dispute->tracking_code = SubmissionCode::generate();
            }
        });
    }

    protected $fillable = [
        'public_record_id',
        'business_affiliation_id',
        'tracking_code',
        'reason',
        'source_url',
        'source_type',
        'status',
        'review_note',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(PublicRecord::class, 'public_record_id');
    }

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(BusinessAffiliation::class, 'business_affiliation_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isAffiliationTarget(): bool
    {
        return $this->business_affiliation_id !== null;
    }
}
