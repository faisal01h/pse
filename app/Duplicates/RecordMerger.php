<?php

namespace App\Duplicates;

use App\Models\Audit;
use App\Models\Business;
use App\Models\IdeologyPosition;
use App\Models\IssueStance;
use App\Models\Party;
use App\Models\PartyLink;
use App\Models\Politician;
use App\Models\PoliticianLink;
use App\Models\RecordMerge;
use App\Models\User;
use App\Plugins\AutoVet\Models\AutoVetRun;
use App\Plugins\RealPolitik\Models\WatchlistItem;
use App\Search\SearchIndexer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Folds one record (source) into another (canonical) and removes the source.
 *
 * Every table that points at politicians, parties or businesses must be handled
 * here; RecordMergerTest scans the schema so a new reference cannot be forgotten.
 */
class RecordMerger
{
    /**
     * Plain foreign-key columns moved by a simple UPDATE, per entity type.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    public const COLUMN_REFERENCES = [
        NameNormalizer::POLITICIAN => [
            'public_records' => ['politician_id'],
            'business_affiliations' => ['politician_id'],
            'issue_stances' => ['politician_id'],
            'ideology_positions' => ['politician_id'],
            'politician_links' => ['politician_a_id', 'politician_b_id'],
            'realpolitik_signals' => ['politician_id'],
        ],
        NameNormalizer::PARTY => [
            'politicians' => ['party_id'],
            'issue_stances' => ['party_id'],
            'ideology_positions' => ['party_id'],
            'party_links' => ['party_a_id', 'party_b_id'],
            'realpolitik_signals' => ['party_id'],
        ],
        NameNormalizer::BUSINESS => [
            'business_affiliations' => ['business_id'],
        ],
    ];

    /**
     * Polymorphic references (type column holds the model class), table => column prefix.
     * audits.auditable_* is deliberately absent: it is history of the removed row.
     *
     * @var array<string, string>
     */
    public const MORPH_REFERENCES = [
        'realpolitik_watchlists' => 'watchable',
        'autovet_runs' => 'subject',
    ];

    /** @var array<string, int> */
    private array $summary = [];

    public function __construct(private readonly NameNormalizer $normalizer) {}

    /**
     * Counts of rows that would move, for the confirmation screen.
     *
     * @return array<string, int>
     */
    public function preview(string $type, Model $source): array
    {
        $counts = [];

        foreach (self::COLUMN_REFERENCES[$type] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $counts[$table] = DB::table($table)
                ->where(function ($query) use ($columns, $source) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, $source->getKey());
                    }
                })
                ->count();
        }

        foreach (self::MORPH_REFERENCES as $table => $prefix) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)
                    ->where($prefix.'_type', $source->getMorphClass())
                    ->where($prefix.'_id', $source->getKey())
                    ->count();
            }
        }

        return array_filter($counts);
    }

    /**
     * @return array<string, int> what moved or was resolved
     */
    public function merge(string $type, Model $canonical, Model $source, ?User $actor = null): array
    {
        $model = DuplicateDetector::modelFor($type);

        if (! $canonical instanceof $model || ! $source instanceof $model) {
            throw new InvalidArgumentException('Data yang digabung harus berjenis sama.');
        }

        if ($canonical->is($source)) {
            throw new InvalidArgumentException('Data tidak bisa digabung dengan dirinya sendiri.');
        }

        $this->summary = [];

        DB::transaction(function () use ($type, $canonical, $source, $actor) {
            $snapshot = $source->withoutRelations()->toArray();
            $sourceName = (string) $source->name;

            match ($type) {
                NameNormalizer::POLITICIAN => $this->mergePolitician($canonical, $source),
                NameNormalizer::PARTY => $this->mergeParty($canonical, $source),
                NameNormalizer::BUSINESS => $this->mergeBusiness($canonical, $source),
            };

            $this->moveMorphReferences($canonical, $source);

            RecordMerge::query()->create([
                'entity_type' => $type,
                'source_id' => $source->getKey(),
                'canonical_id' => $canonical->getKey(),
                'source_name' => $sourceName,
                'snapshot' => $snapshot,
                'summary' => $this->summary,
                'user_id' => $actor?->id,
            ]);

            Audit::query()->create([
                'user_id' => $actor?->id,
                'action' => Audit::ACTION_MERGED,
                'subject' => $sourceName.' → '.$canonical->name,
                'auditable_type' => $canonical::class,
                'auditable_id' => $canonical->getKey(),
                'trigger' => Audit::TRIGGER_MANUAL,
                'status' => Audit::STATUS_RECORDED,
                'proposals_created' => 0,
                'message' => 'Data ganda digabung: #'.$source->getKey().' ke #'.$canonical->getKey().'.',
                'changes' => ['source' => $snapshot, 'moved' => $this->summary],
            ]);

            $source->delete();
            $canonical->refresh();
        });

        $this->reindex($type, $canonical);

        return $this->summary;
    }

    private function mergePolitician(Politician $canonical, Politician $source): void
    {
        $canonical->fill(array_filter([
            'picture_url' => $canonical->picture_url ?: $source->picture_url,
            'party_id' => $canonical->party_id ?: $source->party_id,
            'status' => max((int) $canonical->status, (int) $source->status),
        ], fn ($value) => $value !== null));
        $canonical->save();

        $this->moveColumns(NameNormalizer::POLITICIAN, $canonical, $source);
        $this->moveWatchlist($canonical, $source);

        $this->resolveStances('politician_id', $canonical);
        $this->resolvePositions('politician_id', $canonical);
        $this->resolveLinks(PoliticianLink::class, 'politician_a_id', 'politician_b_id', PoliticianLink::MUTUAL, $canonical);
    }

    private function mergeParty(Party $canonical, Party $source): void
    {
        if (! $canonical->logo_path && $source->logo_path) {
            $canonical->logo_path = $source->logo_path;
            $canonical->save();
            // The logo file now belongs to the canonical record; stop the source row from owning it.
            $source->logo_path = null;
            $source->saveQuietly();
        }

        $this->moveColumns(NameNormalizer::PARTY, $canonical, $source);
        $this->moveWatchlist($canonical, $source);

        $this->resolveStances('party_id', $canonical);
        $this->resolvePositions('party_id', $canonical);
        $this->resolveLinks(PartyLink::class, 'party_a_id', 'party_b_id', PartyLink::MUTUAL, $canonical);
    }

    private function mergeBusiness(Business $canonical, Business $source): void
    {
        if (! $canonical->sector && $source->sector) {
            $canonical->sector = $source->sector;
        }
        $canonical->status = max((int) $canonical->status, (int) $source->status);
        $canonical->save();

        $this->moveColumns(NameNormalizer::BUSINESS, $canonical, $source);
    }

    private function moveColumns(string $type, Model $canonical, Model $source): void
    {
        foreach (self::COLUMN_REFERENCES[$type] as $table => $columns) {
            foreach ($columns as $column) {
                $moved = DB::table($table)
                    ->where($column, $source->getKey())
                    ->update([$column => $canonical->getKey()]);

                if ($moved > 0) {
                    $this->summary[$table] = ($this->summary[$table] ?? 0) + $moved;
                }
            }
        }
    }

    private function moveWatchlist(Model $canonical, Model $source): void
    {
        $class = $canonical->getMorphClass();

        foreach (WatchlistItem::query()->where('watchable_type', $class)->where('watchable_id', $source->getKey())->get() as $item) {
            $already = WatchlistItem::query()
                ->where('user_id', $item->user_id)
                ->where('watchable_type', $class)
                ->where('watchable_id', $canonical->getKey())
                ->exists();

            if ($already) {
                $item->delete();
            } else {
                $item->update(['watchable_id' => $canonical->getKey()]);
            }

            $this->summary['realpolitik_watchlists'] = ($this->summary['realpolitik_watchlists'] ?? 0) + 1;
        }
    }

    private function moveMorphReferences(Model $canonical, Model $source): void
    {
        // Watchlists were handled above (they need de-duplication per user).
        if (Schema::hasTable('autovet_runs')) {
            $moved = AutoVetRun::query()
                ->where('subject_type', $source->getMorphClass())
                ->where('subject_id', $source->getKey())
                ->update(['subject_id' => $canonical->getKey()]);

            if ($moved > 0) {
                $this->summary['autovet_runs'] = $moved;
            }
        }
    }

    /**
     * After reassignment a figure/party may hold two open stances on one issue; keep the
     * newest open and close the older ones the day before the newer one starts.
     */
    private function resolveStances(string $ownerColumn, Model $canonical): void
    {
        $rows = IssueStance::query()->where($ownerColumn, $canonical->getKey())->orderBy('id')->get();

        $this->resolveGroups($rows->groupBy('issue_id'), IssueStance::class, fn (IssueStance $row) => (string) $row->position);
    }

    private function resolvePositions(string $ownerColumn, Model $canonical): void
    {
        $rows = IdeologyPosition::query()->where($ownerColumn, $canonical->getKey())->orderBy('id')->get();

        $this->resolveGroups($rows->groupBy('axis'), IdeologyPosition::class, fn (IdeologyPosition $row) => (string) $row->score, function (Model $keep, Model $drop) {
            DB::table('ideology_position_public_record')
                ->where('ideology_position_id', $drop->getKey())
                ->get()
                ->each(function ($pivot) use ($keep) {
                    $exists = DB::table('ideology_position_public_record')
                        ->where('ideology_position_id', $keep->getKey())
                        ->where('public_record_id', $pivot->public_record_id)
                        ->exists();

                    if (! $exists) {
                        DB::table('ideology_position_public_record')
                            ->where('id', $pivot->id)
                            ->update(['ideology_position_id' => $keep->getKey()]);
                    }
                });
        });
    }

    /**
     * @param  array<int, string>  $mutual
     */
    private function resolveLinks(string $class, string $aColumn, string $bColumn, array $mutual, Model $canonical): void
    {
        /** @var Model $class */
        $class::query()->whereColumn($aColumn, $bColumn)->where($aColumn, $canonical->getKey())->delete();

        foreach ($class::query()->where(fn ($query) => $query->where($aColumn, $canonical->getKey())->orWhere($bColumn, $canonical->getKey()))->get() as $link) {
            if (in_array($link->type, $mutual, true) && $link->{$aColumn} > $link->{$bColumn}) {
                $class::query()->whereKey($link->getKey())->update([
                    $aColumn => $link->{$bColumn},
                    $bColumn => $link->{$aColumn},
                ]);
            }
        }

        $rows = $class::query()
            ->where(fn ($query) => $query->where($aColumn, $canonical->getKey())->orWhere($bColumn, $canonical->getKey()))
            ->orderBy('id')
            ->get();

        $this->resolveGroups(
            $rows->groupBy(fn ($row) => $row->type.'|'.$row->{$aColumn}.'|'.$row->{$bColumn}),
            $class,
            fn ($row) => (string) $row->summary,
        );
    }

    /**
     * @param  Collection<int|string, Collection<int, Model>>  $groups
     * @param  callable(Model): string  $valueOf
     * @param  (callable(Model, Model): void)|null  $absorb  called with (kept, dropped) before a duplicate is deleted
     */
    private function resolveGroups(Collection $groups, string $class, callable $valueOf, ?callable $absorb = null): void
    {
        foreach ($groups as $rows) {
            // 1. Identical interval + value: keep one, drop the copies.
            $kept = [];
            foreach ($rows as $row) {
                $signature = $this->dateKey($row->effective_from).'|'.$this->dateKey($row->ended_at).'|'.$valueOf($row);

                if (isset($kept[$signature])) {
                    if ($absorb) {
                        $absorb($kept[$signature], $row);
                    }
                    $row->delete();
                    $this->bump('duplikat_dihapus');

                    continue;
                }

                $kept[$signature] = $row;
            }

            // 2. Several open intervals: close all but the newest.
            $open = collect($kept)
                ->filter(fn (Model $row) => $row->ended_at === null)
                ->sortBy(fn (Model $row) => $this->dateKey($row->effective_from).sprintf('%012d', $row->getKey()))
                ->values();

            for ($i = 0; $i < $open->count() - 1; $i++) {
                $row = $open[$i];
                $next = $open[$i + 1];
                $end = $next->effective_from ? Carbon::parse($next->effective_from)->subDay() : null;

                if ($row->effective_from && ($end === null || $end->lt(Carbon::parse($row->effective_from)))) {
                    $end = Carbon::parse($row->effective_from);
                }

                $class::query()->whereKey($row->getKey())->update([
                    'ended_at' => ($end ?? Carbon::today())->toDateString(),
                ]);
                $this->bump('interval_ditutup');
            }
        }
    }

    private function dateKey(mixed $date): string
    {
        return $date ? Carbon::parse($date)->toDateString() : '';
    }

    private function bump(string $key): void
    {
        $this->summary[$key] = ($this->summary[$key] ?? 0) + 1;
    }

    private function reindex(string $type, Model $canonical): void
    {
        try {
            $indexer = app(SearchIndexer::class);

            if ($canonical instanceof Politician) {
                $indexer->syncPolitician($canonical);
                $indexer->syncRecordsForPolitician($canonical);
                $indexer->syncBusinessesForPolitician($canonical);
            } elseif ($canonical instanceof Party) {
                $indexer->syncParty($canonical);
            } elseif ($canonical instanceof Business) {
                $indexer->syncBusiness($canonical);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
