<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicRecordTopic extends Model
{
    use RecordsChanges;

    protected $fillable = [
        'public_record_id',
        'topic_id',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(PublicRecord::class, 'public_record_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
