<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_radar_sightings', function (Blueprint $table) {
            $table->id();
            $table->string('feed_url', 2048);
            $table->string('feed_url_hash', 64);
            $table->string('guid', 512);
            $table->string('link', 2048)->nullable();
            $table->string('title', 512)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('public_record_id')->nullable()->constrained('public_records')->nullOnDelete();
            $table->timestamps();

            $table->unique(['feed_url_hash', 'guid']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_radar_sightings');
    }
};
