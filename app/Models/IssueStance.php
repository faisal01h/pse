<?php

namespace App\Models;

use App\Models\Concerns\HasTemporalInterval;
use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueStance extends Model
{
    use HasTemporalInterval, RecordsChanges;

    public const SUPPORT = 'dukung';

    public const OPPOSE = 'tolak';

    public const MIXED = 'campuran';

    public const LABELS = [
        self::SUPPORT => 'Dukung',
        self::OPPOSE => 'Tolak',
        self::MIXED => 'Campuran',
    ];

    protected $fillable = [
        'issue_id',
        'politician_id',
        'party_id',
        'position',
        'note',
        'source_url',
        'source_type',
        'occurred_on',
        'effective_from',
        'ended_at',
        'user_id',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'effective_from' => 'date',
        'ended_at' => 'date',
        'issue_id' => 'integer',
        'politician_id' => 'integer',
        'party_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public static function positionKeys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function formPositions(): array
    {
        $positions = [];
        foreach (self::LABELS as $key => $label) {
            $positions[] = ['key' => $key, 'label' => $label];
        }

        return $positions;
    }

    public function positionLabel(): string
    {
        return self::LABELS[$this->position] ?? $this->position;
    }
}
