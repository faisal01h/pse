<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_documents')) {
            return;
        }

        Schema::create('search_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('label');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body');
            $table->string('href');
            $table->string('image_url')->nullable();
            $table->json('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->unique(['type', 'source_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('search_documents', function (Blueprint $table) {
                $table->fullText(['title', 'body']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_documents');
    }
};
