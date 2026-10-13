<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This file is ordered before the legacy supporters migration, so a fresh
     * install creates both warehouse tables here.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('politicians')) {
            Schema::create('politicians', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('picture_url')->nullable();
                $table->string('position')->nullable();
                $table->string('institution')->nullable();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('public_records')) {
            Schema::create('public_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('politician_id')->constrained()->cascadeOnDelete();
                $table->string('category');
                $table->text('summary')->nullable();
                $table->text('quote')->nullable();
                $table->string('source_url');
                $table->string('source_type');
                $table->date('occurred_on')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('public_records');
        Schema::dropIfExists('politicians');
        Schema::dropIfExists('parties');
    }
};
