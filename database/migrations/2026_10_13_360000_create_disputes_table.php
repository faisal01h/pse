<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages')) {
            DB::table('pages')->where('slug', 'dispute')->delete();
        }

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_record_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('source_url', 2048);
            $table->string('source_type', 100);
            $table->string('status', 16)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
