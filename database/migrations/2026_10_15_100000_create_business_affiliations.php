<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sector')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
            $table->unique('name');
        });

        Schema::create('business_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('ownership_percent', 5, 2)->nullable();
            $table->text('summary');
            $table->string('source_url', 2048);
            $table->string('source_type', 100);
            $table->date('occurred_on')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('check_source')->default(false);
            $table->boolean('check_wording')->default(false);
            $table->boolean('check_category')->default(false);
            $table->boolean('check_duplicate')->default(false);
            $table->boolean('check_legal_label')->default(false);
            $table->string('tracking_code_hash', 64)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_affiliation_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_affiliation_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 127);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_affiliation_attachments');
        Schema::dropIfExists('business_affiliations');
        Schema::dropIfExists('businesses');
    }
};
