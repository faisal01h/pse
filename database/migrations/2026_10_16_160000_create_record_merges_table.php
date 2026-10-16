<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_merges', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('canonical_id');
            $table->string('source_name');
            $table->json('snapshot')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['entity_type', 'source_id']);
            $table->index(['entity_type', 'canonical_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_merges');
    }
};
