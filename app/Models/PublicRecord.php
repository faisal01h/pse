<?php

namespace App\Models;

use App\Models\Concerns\RecordsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicRecord extends Model
{
    use HasFactory, RecordsChanges;

    public const CATEGORY_STATEMENT = 'statement';

    public const CATEGORY_ALLEGATION = 'allegation';

    public const CATEGORY_CONVICTION = 'conviction';

    public const CATEGORIES = [
        self::CATEGORY_STATEMENT,
        self::CATEGORY_ALLEGATION,
        self::CATEGORY_CONVICTION,
    ];

    public const STATUS_PENDING = 0;

    public const STATUS_PUBLISHED = 1;

    public const STATUS_REJECTED = 2;

    protected $fillable = [
        'politician_id',
        'category',
        'summary',
        'quote',
        'source_url',
        'source_type',
        'occurred_on',
        'status',
        'review_note',
        'reviewed_at',
        'reviewed_by',
        'check_source',
        'check_wording',
        'check_category',
        'check_duplicate',
        'check_legal_label',
        'reliability_level',
        'autovet_draft',
        'tracking_code_hash',
    ];

    protected $hidden = [
        'tracking_code_hash',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'reviewed_at' => 'datetime',
        'check_source' => 'boolean',
        'check_wording' => 'boolean',
        'check_category' => 'boolean',
        'check_duplicate' => 'boolean',
        'check_legal_label' => 'boolean',
        'autovet_draft' => 'array',
    ];

    public function publicUrl(): string
    {
        return route('records.show', $this);
    }

    public function publicPath(): string
    {
        return '/records/'.$this->id;
    }

    public function politician(): BelongsTo
    {
        return $this->belongsTo(Politician::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RecordAttachment::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'public_record_topics')->withTimestamps()->orderBy('name');
    }

    public function recordTopics(): HasMany
    {
        return $this->hasMany(PublicRecordTopic::class);
    }

    /**
     * @param  array<int, string>  $names
     */
    public function assignTopics(array $names): void
    {
        $ids = collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->map(fn ($name) => Topic::findOrCreateByName($name)->id)
            ->values();

        $current = $this->recordTopics()->get();

        $current->whereNotIn('topic_id', $ids->all())->each->delete();

        $have = $current->pluck('topic_id');

        foreach ($ids as $id) {
            if (! $have->contains($id)) {
                $this->recordTopics()->create(['topic_id' => $id]);
            }
        }
    }

    /**
     * @param  array<int, string>  $names
     */
    public function addTopics(array $names): void
    {
        foreach ($names as $name) {
            $trimmed = trim((string) $name);
            if ($trimmed === '') {
                continue;
            }

            $topic = Topic::findOrCreateByName($trimmed);
            if (! $this->recordTopics()->where('topic_id', $topic->id)->exists()) {
                $this->recordTopics()->create(['topic_id' => $topic->id]);
            }
        }
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(RelationshipProposal::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }
}
