<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyLink extends Model
{
    use RecordsChanges;

    public const TYPE_COALITION = 'koalisi';

    public const TYPE_OPPOSITION = 'oposisi';

    public const TYPE_MERGED_INTO = 'bergabung_ke';

    public const TYPE_SPLIT_FROM = 'pecahan_dari';

    public const MUTUAL = [
        self::TYPE_COALITION,
        self::TYPE_OPPOSITION,
    ];

    public const LABELS = [
        self::TYPE_COALITION => 'Koalisi',
        self::TYPE_OPPOSITION => 'Oposisi',
        self::TYPE_MERGED_INTO => 'Bergabung ke',
        self::TYPE_SPLIT_FROM => 'Pecahan dari',
    ];

    protected $fillable = [
        'party_a_id',
        'party_b_id',
        'type',
        'summary',
        'source_url',
        'source_type',
        'occurred_on',
        'user_id',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'party_a_id' => 'integer',
        'party_b_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function partyA(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_a_id');
    }

    public function partyB(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_b_id');
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

    public static function endpoints(string $type, int $fromId, int $toId): array
    {
        if (in_array($type, self::MUTUAL, true)) {
            return [min($fromId, $toId), max($fromId, $toId)];
        }

        return [$fromId, $toId];
    }

    public function labelFrom(int $viewerId): string
    {
        if ($this->party_a_id === $viewerId) {
            return self::LABELS[$this->type] ?? $this->type;
        }

        return match ($this->type) {
            self::TYPE_MERGED_INTO => 'Bergabung dari',
            self::TYPE_SPLIT_FROM => 'Pecahan menjadi',
            default => self::LABELS[$this->type] ?? $this->type,
        };
    }
}
