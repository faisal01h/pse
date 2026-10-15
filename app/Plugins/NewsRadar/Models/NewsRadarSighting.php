<?php

namespace App\Plugins\NewsRadar\Models;

use App\Models\PublicRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsRadarSighting extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_FAILED = 'failed';

    protected $table = 'news_radar_sightings';

    protected $fillable = [
        'feed_url',
        'feed_url_hash',
        'guid',
        'link',
        'title',
        'status',
        'note',
        'public_record_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $sighting) {
            if ($sighting->feed_url) {
                $sighting->feed_url_hash = hash('sha256', $sighting->feed_url);
            }
        });
    }

    public function publicRecord(): BelongsTo
    {
        return $this->belongsTo(PublicRecord::class);
    }
}
