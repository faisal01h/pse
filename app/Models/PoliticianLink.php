<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoliticianLink extends Model
{
    use RecordsChanges;

    public const TYPE_ALLY = 'sekutu';

    public const TYPE_RIVAL = 'lawan';

    public const TYPE_SUPPORT = 'mendukung';

    public const MUTUAL = [
        self::TYPE_ALLY,
        self::TYPE_RIVAL,
    ];

    public const LABELS = [
        self::TYPE_ALLY => 'Sekutu',
        self::TYPE_RIVAL => 'Lawan',
        self::TYPE_SUPPORT => 'Mendukung',
    ];

    protected $fillable = [
        'politician_a_id',
        'politician_b_id',
        'type',
        'summary',
        'source_url',
        'source_type',
        'occurred_on',
        'user_id',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'politician_a_id' => 'integer',
        'politician_b_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function politicianA(): BelongsTo
    {
        return $this->belongsTo(Politician::class, 'politician_a_id');
    }

    public function politicianB(): BelongsTo
    {
        return $this->belongsTo(Politician::class, 'politician_b_id');
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
        if ($this->type === self::TYPE_SUPPORT && $this->politician_a_id !== $viewerId) {
            return 'Didukung oleh';
        }

        return self::LABELS[$this->type] ?? $this->type;
    }
}
