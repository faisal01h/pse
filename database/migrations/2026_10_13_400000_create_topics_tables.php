<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('public_record_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['public_record_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_record_topics');
        Schema::dropIfExists('topics');
    }
};
