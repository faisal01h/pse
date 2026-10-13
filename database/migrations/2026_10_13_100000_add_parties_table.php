<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the free-text party column with a foreign key.
     */
    public function up()
    {
        if (! Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('politicians')) {
            return;
        }

        if (! Schema::hasColumn('politicians', 'party_id')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('politicians', 'party')) {
            return;
        }

        $names = DB::table('politicians')
            ->whereNotNull('party')
            ->where('party', '!=', '')
            ->distinct()
            ->pluck('party');

        foreach ($names as $name) {
            $trimmed = trim((string) $name);
            $normalized = mb_strtolower($trimmed);

            if ($trimmed === '' || in_array($normalized, ['independen', 'independent'], true)) {
                continue;
            }

            $partyId = DB::table('parties')->whereRaw('LOWER(name) = ?', [$normalized])->value('id');

            if (! $partyId) {
                $partyId = DB::table('parties')->insertGetId([
                    'name' => $trimmed,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('politicians')->where('party', $name)->update(['party_id' => $partyId]);
        }

        Schema::table('politicians', function (Blueprint $table) {
            $table->dropColumn('party');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (! Schema::hasTable('politicians')) {
            Schema::dropIfExists('parties');

            return;
        }

        if (! Schema::hasColumn('politicians', 'party')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->string('party')->nullable();
            });
        }

        if (Schema::hasColumn('politicians', 'party_id') && Schema::hasTable('parties')) {
            $politicians = DB::table('politicians')->whereNotNull('party_id')->get(['id', 'party_id']);

            foreach ($politicians as $politician) {
                $name = DB::table('parties')->where('id', $politician->party_id)->value('name');
                DB::table('politicians')->where('id', $politician->id)->update(['party' => $name]);
            }

            Schema::table('politicians', function (Blueprint $table) {
                $table->dropConstrainedForeignId('party_id');
            });
        }

        Schema::dropIfExists('parties');
    }
};
