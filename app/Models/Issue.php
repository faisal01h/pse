<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use RecordsChanges;

    protected $fillable = [
        'name',
        'description',
    ];

    public function stances(): HasMany
    {
        return $this->hasMany(IssueStance::class);
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
