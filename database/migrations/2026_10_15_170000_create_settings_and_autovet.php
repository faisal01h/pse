<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('autovet_runs', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('status', 32)->default('queued');
            $table->string('mode', 32)->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('public_records', function (Blueprint $table) {
            $table->json('autovet_draft')->nullable()->after('reliability_level');
        });

        Schema::table('business_affiliations', function (Blueprint $table) {
            $table->json('autovet_draft')->nullable()->after('reliability_level');
        });
    }

    public function down(): void
    {
        Schema::table('public_records', function (Blueprint $table) {
            $table->dropColumn('autovet_draft');
        });

        Schema::table('business_affiliations', function (Blueprint $table) {
            $table->dropColumn('autovet_draft');
        });

        Schema::dropIfExists('autovet_runs');
        Schema::dropIfExists('settings');
    }
};
