<?php

use App\Models\IdeologyPosition;
use App\Models\RelationshipProposal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ideology_position_public_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ideology_position_id')->constrained('ideology_positions')->cascadeOnDelete();
            $table->foreignId('public_record_id')->constrained('public_records')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ideology_position_id', 'public_record_id'], 'ideology_record_unique');
        });

        $accepted = RelationshipProposal::query()
            ->where('kind', RelationshipProposal::KIND_AXIS)
            ->where('status', RelationshipProposal::STATUS_ACCEPTED)
            ->with('record:id,politician_id')
            ->get();

        foreach ($accepted as $proposal) {
            $record = $proposal->record;
            $axis = $proposal->payload['axis'] ?? null;
            if (! $record || ! $record->politician_id || ! is_string($axis) || $axis === '') {
                continue;
            }

            $position = IdeologyPosition::query()
                ->where('politician_id', $record->politician_id)
                ->where('axis', $axis)
                ->first();

            if (! $position) {
                continue;
            }

            DB::table('ideology_position_public_record')->insertOrIgnore([
                'ideology_position_id' => $position->id,
                'public_record_id' => $record->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ideology_position_public_record');
    }
};
