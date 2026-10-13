<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory, RecordsChanges;

    protected $fillable = [
        'name',
        'logo_path',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function politicians(): HasMany
    {
        return $this->hasMany(Politician::class);
    }

    public function ideologyPositions(): HasMany
    {
        return $this->hasMany(IdeologyPosition::class);
    }

    public function issueStances(): HasMany
    {
        return $this->hasMany(IssueStance::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http')) {
            return $this->logo_path;
        }

        return '/storage/'.$this->logo_path;
    }
}
