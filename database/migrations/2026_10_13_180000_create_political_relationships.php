<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ideology_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('axis', 32);
            $table->string('position', 32);
            $table->text('note');
            $table->string('source_url', 2048);
            $table->string('source_type', 100);
            $table->date('occurred_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['politician_id', 'axis']);
            $table->unique(['party_id', 'axis']);
        });

        Schema::create('politician_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('politician_a_id')->constrained('politicians')->cascadeOnDelete();
            $table->foreignId('politician_b_id')->constrained('politicians')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('summary');
            $table->string('source_url', 2048);
            $table->string('source_type', 100);
            $table->date('occurred_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['politician_a_id', 'politician_b_id', 'type']);
        });

        Schema::create('party_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_a_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignId('party_b_id')->constrained('parties')->cascadeOnDelete();
            $table->string('type', 32);
            $table->text('summary');
            $table->string('source_url', 2048);
            $table->string('source_type', 100);
            $table->date('occurred_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['party_a_id', 'party_b_id', 'type']);
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('issue_stances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('politician_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('position', 32);
            $table->text('note');
            $table->string('source_url', 2048);
            $table->string('source_type', 100);
            $table->date('occurred_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['politician_id', 'issue_id']);
            $table->unique(['party_id', 'issue_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('issue_stances');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('party_links');
        Schema::dropIfExists('politician_links');
        Schema::dropIfExists('ideology_positions');
    }
};
