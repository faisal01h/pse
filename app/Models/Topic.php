<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Topic extends Model
{
    use RecordsChanges;

    public const AI_CHECKED = 'AI Checked';

    public const AUTO_ADDED = 'Auto-added';

    protected $fillable = [
        'name',
    ];

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(PublicRecord::class, 'public_record_topics')->withTimestamps();
    }

    public static function findOrCreateByName(string $name): self
    {
        $trimmed = trim($name);
        $existing = self::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmed)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create(['name' => $trimmed]);
    }
}
