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
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->date('effective_from')->nullable()->after('occurred_on');
                $blueprint->date('ended_at')->nullable()->after('effective_from');
                $blueprint->index(['ended_at'], $table.'_ended_at_index');
            });
        }

        Schema::table('issue_stances', function (Blueprint $table) {
            $table->dropUnique(['politician_id', 'issue_id']);
            $table->dropUnique(['party_id', 'issue_id']);
            $table->index(['politician_id', 'issue_id', 'ended_at'], 'issue_stances_politician_issue_open_index');
            $table->index(['party_id', 'issue_id', 'ended_at'], 'issue_stances_party_issue_open_index');
        });

        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->dropUnique(['politician_id', 'axis']);
            $table->dropUnique(['party_id', 'axis']);
            $table->index(['politician_id', 'axis', 'ended_at'], 'ideology_positions_politician_axis_open_index');
            $table->index(['party_id', 'axis', 'ended_at'], 'ideology_positions_party_axis_open_index');
        });

        Schema::table('politician_links', function (Blueprint $table) {
            $table->dropUnique(['politician_a_id', 'politician_b_id', 'type']);
            $table->index(['politician_a_id', 'politician_b_id', 'type', 'ended_at'], 'politician_links_pair_type_open_index');
        });

        Schema::table('party_links', function (Blueprint $table) {
            $table->dropUnique(['party_a_id', 'party_b_id', 'type']);
            $table->index(['party_a_id', 'party_b_id', 'type', 'ended_at'], 'party_links_pair_type_open_index');
        });

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

    private function backfill(string $table): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table) {
            foreach ($rows as $row) {
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
