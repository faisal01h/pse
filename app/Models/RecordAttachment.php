<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RecordAttachment extends Model
{
    use RecordsChanges;

    protected $fillable = [
        'public_record_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $hidden = [
        'path',
    ];

    protected $appends = [
        'url',
        'kind',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(PublicRecord::class, 'public_record_id');
    }

    public function getUrlAttribute(): string
    {
        return route('attachments.show', $this);
    }

    public function getKindAttribute(): string
    {
        if (str_starts_with($this->mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($this->mime, 'video/')) {
            return 'video';
        }

        return 'pdf';
    }

    public function deleteFile(): void
    {
        Storage::disk('local')->delete($this->path);
    }
}
