<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory, RecordsChanges;

    public const STATUS_DRAFT = 0;

    public const STATUS_PUBLISHED = 1;

    protected $fillable = [
        'name',
        'sector',
        'status',
    ];

    public function affiliations(): HasMany
    {
        return $this->hasMany(BusinessAffiliation::class);
    }

    public static function findOrCreateByName(string $name, int $status = self::STATUS_DRAFT): self
    {
        $name = trim($name);
        $existing = static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return static::create([
            'name' => $name,
            'status' => $status,
        ]);
    }
}
