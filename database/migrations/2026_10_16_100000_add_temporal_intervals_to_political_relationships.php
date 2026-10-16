<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['issue_stances', 'ideology_positions', 'politician_links', 'party_links'] as $table) {
            if (! Schema::hasColumn($table, 'effective_from')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->date('effective_from')->nullable()->after('occurred_on');
                    $blueprint->date('ended_at')->nullable()->after('effective_from');
                    $blueprint->index(['ended_at'], $table.'_ended_at_index');
                });
            }
        }

        $this->replaceUniqueWithOpenIndex(
            'issue_stances',
            foreignKeys: ['politician_id' => 'politicians', 'party_id' => 'parties'],
            dropUniques: [
                ['politician_id', 'issue_id'],
                ['party_id', 'issue_id'],
            ],
            openIndexes: [
                'issue_stances_politician_issue_open_index' => ['politician_id', 'issue_id', 'ended_at'],
                'issue_stances_party_issue_open_index' => ['party_id', 'issue_id', 'ended_at'],
            ],
        );

        $this->replaceUniqueWithOpenIndex(
            'ideology_positions',
            foreignKeys: ['politician_id' => 'politicians', 'party_id' => 'parties'],
            dropUniques: [
                ['politician_id', 'axis'],
                ['party_id', 'axis'],
            ],
            openIndexes: [
                'ideology_positions_politician_axis_open_index' => ['politician_id', 'axis', 'ended_at'],
                'ideology_positions_party_axis_open_index' => ['party_id', 'axis', 'ended_at'],
            ],
        );

        $this->replaceUniqueWithOpenIndex(
            'politician_links',
            foreignKeys: [
                'politician_a_id' => 'politicians',
                'politician_b_id' => 'politicians',
            ],
            dropUniques: [
                ['politician_a_id', 'politician_b_id', 'type'],
            ],
            openIndexes: [
                'politician_links_pair_type_open_index' => ['politician_a_id', 'politician_b_id', 'type', 'ended_at'],
            ],
        );

        $this->replaceUniqueWithOpenIndex(
            'party_links',
            foreignKeys: [
                'party_a_id' => 'parties',
                'party_b_id' => 'parties',
            ],
            dropUniques: [
                ['party_a_id', 'party_b_id', 'type'],
            ],
            openIndexes: [
                'party_links_pair_type_open_index' => ['party_a_id', 'party_b_id', 'type', 'ended_at'],
            ],
        );

        $this->backfill('issue_stances');
        $this->backfill('ideology_positions');
        $this->backfill('politician_links');
        $this->backfill('party_links');
    }

    public function down(): void
    {
        Schema::table('issue_stances', function (Blueprint $table) {
            $table->dropIndex('issue_stances_politician_issue_open_index');
            $table->dropIndex('issue_stances_party_issue_open_index');
            $table->dropIndex('issue_stances_ended_at_index');
            $table->dropColumn(['effective_from', 'ended_at']);
        });

        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->dropIndex('ideology_positions_politician_axis_open_index');
            $table->dropIndex('ideology_positions_party_axis_open_index');
            $table->dropIndex('ideology_positions_ended_at_index');
            $table->dropColumn(['effective_from', 'ended_at']);
        });

        Schema::table('politician_links', function (Blueprint $table) {
            $table->dropIndex('politician_links_pair_type_open_index');
            $table->dropIndex('politician_links_ended_at_index');
            $table->dropColumn(['effective_from', 'ended_at']);
        });

        Schema::table('party_links', function (Blueprint $table) {
            $table->dropIndex('party_links_pair_type_open_index');
            $table->dropIndex('party_links_ended_at_index');
            $table->dropColumn(['effective_from', 'ended_at']);
        });
    }

    /**
     * @param  array<string, string>  $foreignKeys  column => referenced table
     * @param  list<list<string>>  $dropUniques
     * @param  array<string, list<string>>  $openIndexes
     */
    private function replaceUniqueWithOpenIndex(
        string $table,
        array $foreignKeys,
        array $dropUniques,
        array $openIndexes,
    ): void {
        $firstOpenIndex = array_key_first($openIndexes);
        if ($firstOpenIndex !== null && $this->hasIndex($table, $firstOpenIndex)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKeys) {
            foreach (array_keys($foreignKeys) as $column) {
                $blueprint->dropForeign([$column]);
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($dropUniques, $openIndexes) {
            foreach ($dropUniques as $columns) {
                $blueprint->dropUnique($columns);
            }

            foreach ($openIndexes as $name => $columns) {
                $blueprint->index($columns, $name);
            }
        });

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKeys) {
            foreach ($foreignKeys as $column => $referenced) {
                $blueprint->foreign($column)
                    ->references('id')
                    ->on($referenced)
                    ->cascadeOnDelete();
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'select 1 as present from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $indexName],
        );

        return $row !== null;
    }

    private function backfill(string $table): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table) {
            foreach ($rows as $row) {
                if (! empty($row->effective_from)) {
                    continue;
                }

                $from = $row->occurred_on
                    ?? (isset($row->created_at) ? substr((string) $row->created_at, 0, 10) : null)
                    ?? now()->toDateString();

                DB::table($table)->where('id', $row->id)->update([
                    'effective_from' => $from,
                    'ended_at' => null,
                ]);
            }
        });
    }
};
