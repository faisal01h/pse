<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('axis');
        });

        $legacy = [
            'negara' => 0,
            'progresif' => 0,
            'pluralis' => 0,
            'campuran' => 50,
            'pasar' => 100,
            'konservatif' => 100,
            'nasionalis' => 100,
        ];

        foreach (DB::table('ideology_positions')->get() as $row) {
            $score = $legacy[$row->position] ?? (is_numeric($row->position) ? (int) $row->position : 50);
            DB::table('ideology_positions')->where('id', $row->id)->update([
                'score' => max(0, min(100, $score)),
            ]);
        }

        foreach (DB::table('relationship_proposals')->where('kind', 'axis')->get() as $row) {
            $payload = json_decode($row->payload, true);
            if (! is_array($payload) || array_key_exists('score', $payload)) {
                continue;
            }

            $payload['score'] = $legacy[$payload['position'] ?? ''] ?? 50;
            unset($payload['position']);
            DB::table('relationship_proposals')->where('id', $row->id)->update([
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    public function down(): void
    {
        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->string('position', 32)->nullable()->after('axis');
        });

        $poles = [
            'ekonomi' => ['negara', 'pasar'],
            'sosial' => ['progresif', 'konservatif'],
            'nasionalisme' => ['pluralis', 'nasionalis'],
        ];

        foreach (DB::table('ideology_positions')->get() as $row) {
            [$left, $right] = $poles[$row->axis] ?? ['campuran', 'campuran'];
            $score = (int) $row->score;
            $position = $score <= 33 ? $left : ($score >= 67 ? $right : 'campuran');
            DB::table('ideology_positions')->where('id', $row->id)->update(['position' => $position]);
        }

        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->dropColumn('score');
        });
    }
};
