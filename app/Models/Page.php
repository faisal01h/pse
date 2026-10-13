<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use RecordsChanges;

    public const FORMAT_MARKDOWN = 'markdown';

    public const FORMAT_RICHTEXT = 'richtext';

    public const FORMATS = [
        self::FORMAT_MARKDOWN,
        self::FORMAT_RICHTEXT,
    ];

    protected $fillable = [
        'title',
        'slug',
        'format',
        'body',
        'pinned',
    ];

    protected $casts = [
        'pinned' => 'boolean',
    ];
}
