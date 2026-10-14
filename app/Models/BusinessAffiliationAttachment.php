<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BusinessAffiliationAttachment extends Model
{
    use RecordsChanges;

    protected $fillable = [
        'business_affiliation_id',
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

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(BusinessAffiliation::class, 'business_affiliation_id');
    }

    public function getUrlAttribute(): string
    {
        return route('affiliation-attachments.show', $this);
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
