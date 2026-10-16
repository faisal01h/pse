<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realpolitik_briefs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->text('summary_md')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('ready');
            $table->string('confidence', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('realpolitik_brief_public_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('situation_brief_id')->constrained('realpolitik_briefs')->cascadeOnDelete();
            $table->foreignId('public_record_id')->constrained('public_records')->cascadeOnDelete();
            $table->unique(['situation_brief_id', 'public_record_id'], 'rp_brief_record_unique');
        });

        Schema::create('realpolitik_signals', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('severity', 16)->default('medium');
            $table->foreignId('politician_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brief_id')->nullable()->constrained('realpolitik_briefs')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'created_at']);
            $table->index(['dismissed_at', 'created_at']);
        });

        Schema::create('realpolitik_watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('watchable_type');
            $table->unsignedBigInteger('watchable_id');
            $table->timestamps();
            $table->unique(['user_id', 'watchable_type', 'watchable_id'], 'rp_watchlist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realpolitik_watchlists');
        Schema::dropIfExists('realpolitik_signals');
        Schema::dropIfExists('realpolitik_brief_public_record');
        Schema::dropIfExists('realpolitik_briefs');
    }
};
