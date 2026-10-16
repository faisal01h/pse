<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordMerge extends Model
{
    protected $fillable = [
        'entity_type',
        'source_id',
        'canonical_id',
        'source_name',
        'snapshot',
        'summary',
        'user_id',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'summary' => 'array',
        'source_id' => 'integer',
        'canonical_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Where a removed record now lives, following chained merges.
     */
    public static function canonicalFor(string $entityType, int $sourceId): ?int
    {
        $seen = [];
        $id = $sourceId;

        while (! in_array($id, $seen, true)) {
            $seen[] = $id;
            $next = static::query()
                ->where('entity_type', $entityType)
                ->where('source_id', $id)
                ->latest('id')
                ->value('canonical_id');

            if ($next === null) {
                return $id === $sourceId ? null : $id;
            }

            $id = (int) $next;
        }

        return null;
    }
}
