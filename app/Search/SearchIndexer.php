<?php

namespace App\Search;

use App\Models\Party;
use App\Models\Politician;
use App\Models\PublicRecord;
use App\Models\PublicRecordTopic;
use App\Models\RecordAttachment;
use App\Models\SearchDocument;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SearchIndexer
{
    private const CATEGORY_LABELS = [
        PublicRecord::CATEGORY_STATEMENT => 'Pernyataan publik',
        PublicRecord::CATEGORY_ALLEGATION => 'Dugaan pelanggaran',
        PublicRecord::CATEGORY_CONVICTION => 'Vonis pengadilan',
    ];

    public function __construct(private Embedder $embedder) {}

    public static function register(): void
    {
        Party::saved(function (Party $party) {
            app(self::class)->syncParty($party);
        });

        Party::deleting(function (Party $party) {
            $indexer = app(self::class);
            $indexer->forget(SearchDocument::TYPE_PARTY, $party->id);

            foreach ($party->politicians as $politician) {
                $politician->party_id = null;
                $politician->setRelation('party', null);
                $indexer->syncPolitician($politician);
            }
        });

        Politician::saved(function (Politician $politician) {
            $indexer = app(self::class);
            $indexer->syncPolitician($politician);
            $indexer->syncRecordsForPolitician($politician);

            if ($politician->party_id) {
                $politician->loadMissing('party');
                if ($politician->party) {
                    $indexer->syncParty($politician->party);
                }
            }
        });

        Politician::deleting(function (Politician $politician) {
            $indexer = app(self::class);
            $indexer->forget(SearchDocument::TYPE_POLITICIAN, $politician->id);

            foreach ($politician->records()->pluck('id') as $recordId) {
                $indexer->forget(SearchDocument::TYPE_RECORD, (int) $recordId);
            }

            if ($politician->party_id) {
                $politician->loadMissing('party');
                if ($politician->party) {
                    $indexer->syncParty($politician->party);
                }
            }
        });

        PublicRecord::saved(function (PublicRecord $record) {
            $indexer = app(self::class);
            $indexer->syncRecord($record);
            $record->loadMissing('politician');

            if ($record->politician) {
                $indexer->syncPolitician($record->politician);
            }
        });

        PublicRecordTopic::saved(function (PublicRecordTopic $link) {
            $record = $link->record;

            if ($record) {
                app(self::class)->syncRecord($record);
            }
        });

        PublicRecordTopic::deleted(function (PublicRecordTopic $link) {
            $record = PublicRecord::query()->find($link->public_record_id);

            if ($record) {
                app(self::class)->syncRecord($record);
            }
        });

        PublicRecord::deleted(function (PublicRecord $record) {
            $indexer = app(self::class);
            $indexer->forget(SearchDocument::TYPE_RECORD, $record->id);
            $record->loadMissing('politician');

            if ($record->politician) {
                $indexer->syncPolitician($record->politician);
            }
        });

        RecordAttachment::saved(function (RecordAttachment $attachment) {
            $attachment->loadMissing('record');

            if ($attachment->record) {
                app(self::class)->syncRecord($attachment->record);
            }
        });

        RecordAttachment::deleted(function (RecordAttachment $attachment) {
            $attachment->loadMissing('record');

            if ($attachment->record) {
                app(self::class)->syncRecord($attachment->record);
            }
        });
    }

    public function reindexAll(): int
    {
        if (! $this->ready()) {
            return 0;
        }

        $count = 0;

        $partyIds = [];
        foreach (Party::query()->with(['politicians' => function ($query) {
            $query->where('status', 1)->orderBy('name');
        }])->orderBy('id')->get() as $party) {
            $this->syncParty($party);
            $partyIds[] = $party->id;
            $count++;
        }

        $politicianIds = [];
        foreach (Politician::query()->with('party')->orderBy('id')->get() as $politician) {
            $this->syncPolitician($politician);
            $politicianIds[] = $politician->id;
            $count++;
        }

        $recordIds = [];
        foreach (PublicRecord::query()->with(['politician.party', 'attachments'])->orderBy('id')->get() as $record) {
            $this->syncRecord($record);
            $recordIds[] = $record->id;
            $count++;
        }

        $this->forgetMissing(SearchDocument::TYPE_PARTY, $partyIds);
        $this->forgetMissing(SearchDocument::TYPE_POLITICIAN, $politicianIds);
        $this->forgetMissing(SearchDocument::TYPE_RECORD, $recordIds);

        return $count;
    }

    public function syncParty(Party $party): void
    {
        if (! $this->ready() || ! $party->id) {
            return;
        }

        $party->loadMissing(['politicians' => function ($query) {
            $query->where('status', 1)->orderBy('name');
        }]);

        $names = $party->politicians->pluck('name')->filter()->implode(', ');
        $body = trim($party->name.($names !== '' ? "\nPolitisi terbit: ".$names : ''));

        $this->upsert(SearchDocument::TYPE_PARTY, $party->id, [
            'label' => 'Partai',
            'title' => $party->name,
            'subtitle' => 'Partai politik',
            'body' => $body,
            'href' => '/?party='.$party->id,
            'image_url' => $party->logo_url,
        ]);
    }

    public function syncPolitician(Politician $politician): void
    {
        if (! $this->ready() || ! $politician->id) {
            return;
        }

        if ((int) $politician->status !== 1) {
            $this->forget(SearchDocument::TYPE_POLITICIAN, $politician->id);

            return;
        }

        $politician->loadMissing('party');
        $party = $politician->party?->name;
        $parts = array_filter([
            $politician->name,
            $politician->position,
            $politician->institution,
            $party,
        ]);

        $this->upsert(SearchDocument::TYPE_POLITICIAN, $politician->id, [
            'label' => 'Politisi',
            'title' => $politician->name,
            'subtitle' => implode(' · ', array_filter([$politician->position, $politician->institution, $party])),
            'body' => implode("\n", $parts),
            'href' => '/politicians/'.$politician->id,
            'image_url' => $politician->photo_url,
        ]);
    }

    public function syncRecordsForPolitician(Politician $politician): void
    {
        if (! $this->ready()) {
            return;
        }

        $politician->loadMissing(['records.attachments', 'records.politician.party']);

        foreach ($politician->records as $record) {
            $record->setRelation('politician', $politician);
            $this->syncRecord($record);
        }
    }

    public function syncRecord(PublicRecord $record): void
    {
        if (! $this->ready() || ! $record->id) {
            return;
        }

        $record->loadMissing(['politician.party', 'attachments', 'topics']);
        $politician = $record->politician;
        $published = (int) $record->status === PublicRecord::STATUS_PUBLISHED
            && $politician
            && (int) $politician->status === 1;

        if (! $published) {
            $this->forget(SearchDocument::TYPE_RECORD, $record->id);

            return;
        }

        $label = self::CATEGORY_LABELS[$record->category] ?? 'Catatan';
        $quote = trim((string) $record->quote);
        $summary = trim((string) $record->summary);
        $title = $quote !== '' ? $quote : $summary;
        $files = $record->attachments->pluck('original_name')->filter()->implode(', ');
        $body = trim(implode("\n", array_filter([
            $label,
            $politician->name,
            $politician->position,
            $politician->institution,
            $politician->party?->name,
            $quote,
            $summary,
            $record->topics->pluck('name')->implode(', '),
            $record->source_type,
            $files,
        ])));

        $date = $record->occurred_on?->format('d-m-Y');

        $this->upsert(SearchDocument::TYPE_RECORD, $record->id, [
            'label' => $label,
            'title' => mb_substr($title !== '' ? $title : $label, 0, 240),
            'subtitle' => implode(' · ', array_filter([$politician->name, $date, $record->source_type])),
            'body' => $body,
            'href' => '/politicians/'.$politician->id.'#record-'.$record->id,
            'image_url' => null,
        ]);
    }

    public function forget(string $type, int $sourceId): void
    {
        if (! $this->ready()) {
            return;
        }

        SearchDocument::query()->where('type', $type)->where('source_id', $sourceId)->delete();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function forgetMissing(string $type, array $ids): void
    {
        $query = SearchDocument::query()->where('type', $type);

        if ($ids === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('source_id', $ids)->delete();
    }

    /**
     * @param  array{label: string, title: string, subtitle: string, body: string, href: string, image_url: ?string}  $attributes
     */
    private function upsert(string $type, int $sourceId, array $attributes): void
    {
        $attributes['title'] = mb_substr($attributes['title'], 0, 255);
        $attributes['subtitle'] = mb_substr((string) $attributes['subtitle'], 0, 255);
        $hash = hash('sha256', $attributes['title']."\n".$attributes['body']);

        $document = SearchDocument::query()->firstOrNew([
            'type' => $type,
            'source_id' => $sourceId,
        ]);

        $sameVector = $document->exists
            && $document->content_hash === $hash
            && $document->embedding_model === $this->embedder->model()
            && is_array($document->embedding);

        if (! $sameVector) {
            try {
                $document->embedding = $this->embedder->embed($attributes['title']."\n".$attributes['title']."\n".$attributes['body']);
                $document->embedding_model = $this->embedder->model();
            } catch (Throwable $exception) {
                report($exception);
                $document->embedding = null;
                $document->embedding_model = null;
            }
        }

        $document->fill($attributes);
        $document->content_hash = $hash;
        $document->save();
    }

    private function ready(): bool
    {
        return Schema::hasTable('search_documents');
    }
}
