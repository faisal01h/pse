<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchDocument extends Model
{
    public const TYPE_POLITICIAN = 'politician';

    public const TYPE_PARTY = 'party';

    public const TYPE_RECORD = 'record';

    protected $fillable = [
        'type',
        'source_id',
        'label',
        'title',
        'subtitle',
        'body',
        'href',
        'image_url',
        'embedding',
        'embedding_model',
        'content_hash',
    ];

    protected $casts = [
        'embedding' => 'array',
        'source_id' => 'integer',
    ];
}
