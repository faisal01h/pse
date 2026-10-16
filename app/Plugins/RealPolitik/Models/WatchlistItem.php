<?php

namespace App\Plugins\RealPolitik\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WatchlistItem extends Model
{
    protected $table = 'realpolitik_watchlists';

    protected $fillable = [
        'user_id',
        'watchable_type',
        'watchable_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function watchable(): MorphTo
    {
        return $this->morphTo();
    }
}
