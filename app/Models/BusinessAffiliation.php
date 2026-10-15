<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessAffiliation extends Model
{
    use HasFactory, RecordsChanges;

    public const TYPE_OWNERSHIP = 'ownership';

    public const TYPE_ENDORSEMENT = 'endorsement';

    public const LABELS = [
        self::TYPE_OWNERSHIP => 'Kepemilikan',
        self::TYPE_ENDORSEMENT => 'Endorsemen',
    ];

    public const STATUS_PENDING = 0;

    public const STATUS_PUBLISHED = 1;

    public const STATUS_REJECTED = 2;

    public const DISPUTE_THRESHOLD_BASE = 10;

    public const DISPUTE_THRESHOLD_MULTIPLIER = 5;

    protected $fillable = [
        'politician_id',
        'business_id',
        'type',
        'ownership_percent',
        'summary',
        'source_url',
        'source_type',
        'occurred_on',
        'status',
        'dispute_threshold',
        'dispute_retract_pending',
        'review_note',
        'reviewed_at',
        'reviewed_by',
        'check_source',
        'check_wording',
        'check_category',
        'check_duplicate',
        'check_legal_label',
        'reliability_level',
        'autovet_draft',
        'tracking_code_hash',
        'user_id',
    ];

    protected $hidden = [
        'tracking_code_hash',
    ];

    protected $casts = [
        'ownership_percent' => 'float',
        'occurred_on' => 'date',
        'reviewed_at' => 'datetime',
        'check_source' => 'boolean',
        'check_wording' => 'boolean',
        'check_category' => 'boolean',
        'check_duplicate' => 'boolean',
        'check_legal_label' => 'boolean',
        'dispute_retract_pending' => 'boolean',
        'dispute_threshold' => 'integer',
        'autovet_draft' => 'array',
    ];

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BusinessAffiliationAttachment::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public static function typeKeys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function formTypes(): array
    {
        $types = [];
        foreach (self::LABELS as $key => $label) {
            $types[] = ['key' => $key, 'label' => $label];
        }

        return $types;
    }

    public function typeLabel(): string
    {
        if ($this->type === self::TYPE_OWNERSHIP) {
            if ($this->ownership_percent === null) {
                return 'Kepemilikan (jumlah tidak diketahui)';
            }

            $percent = rtrim(rtrim(number_format((float) $this->ownership_percent, 2, '.', ''), '0'), '.');

            if ((float) $this->ownership_percent >= 100) {
                return 'Kepemilikan penuh (100%)';
            }

            return 'Kepemilikan '.$percent.'%';
        }

        return self::LABELS[$this->type] ?? $this->type;
    }

    public function disputeThreshold(): int
    {
        $threshold = (int) ($this->dispute_threshold ?: self::DISPUTE_THRESHOLD_BASE);

        return max(self::DISPUTE_THRESHOLD_BASE, $threshold);
    }

    /**
     * Pending disputes for this figure+business pair (any affiliation type).
     */
    public function pendingDisputesForPair(): int
    {
        return Dispute::query()
            ->where('status', Dispute::STATUS_PENDING)
            ->whereHas('affiliation', function ($query) {
                $query->where('politician_id', $this->politician_id)
                    ->where('business_id', $this->business_id);
            })
            ->count();
    }

    /**
     * Unpublish this affiliation (and siblings for the same figure+business) when
     * pending disputes meet the current threshold.
     */
    public function autoRetractIfThresholdMet(): bool
    {
        if ((int) $this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->pendingDisputesForPair() < $this->disputeThreshold()) {
            return false;
        }

        $siblings = static::query()
            ->where('politician_id', $this->politician_id)
            ->where('business_id', $this->business_id)
            ->where('status', self::STATUS_PUBLISHED)
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->fill([
                'status' => self::STATUS_PENDING,
                'dispute_retract_pending' => true,
                'review_note' => 'Ditarik otomatis karena sanggahan pending mencapai ambang '.$sibling->disputeThreshold().' untuk pasangan tokoh dan bisnis ini.',
                'reviewed_at' => now(),
            ]);
            $sibling->save();
        }

        $this->refresh();

        $politician = $this->politician;
        if ($politician) {
            $stillVisible = $politician->records()
                ->where('status', PublicRecord::STATUS_PUBLISHED)
                ->exists()
                || $politician->businessAffiliations()
                    ->where('status', self::STATUS_PUBLISHED)
                    ->exists();
            $politician->status = $stillVisible ? 1 : 0;
            $politician->save();
        }

        return true;
    }

    /**
     * After admin rejects disputes and republishes, raise the next retract bar 5×.
     */
    public function applyRepublishAfterDisputeRetract(): void
    {
        if (! $this->dispute_retract_pending) {
            return;
        }

        $this->dispute_threshold = $this->disputeThreshold() * self::DISPUTE_THRESHOLD_MULTIPLIER;
        $this->dispute_retract_pending = false;
    }
}
