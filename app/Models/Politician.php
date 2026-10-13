<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Politician extends Model
{
    use HasFactory, RecordsChanges;

    protected $fillable = [
        'name',
        'picture_url',
        'position',
        'institution',
        'party_id',
        'status',
    ];

    protected $appends = [
        'photo_url',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(PublicRecord::class);
    }

    public function ideologyPositions(): HasMany
    {
        return $this->hasMany(IdeologyPosition::class);
    }

    public function issueStances(): HasMany
    {
        return $this->hasMany(IssueStance::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->picture_url) {
            return null;
        }

        if (str_starts_with($this->picture_url, 'http')) {
            return $this->picture_url;
        }

        return '/storage/'.$this->picture_url;
    }
}
