<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('connection');
            $table->string('queue')->default('default');
            $table->string('status')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_tries')->nullable();
            $table->unsignedInteger('timeout')->nullable();
            $table->json('summary')->nullable();
            $table->longText('exception')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
