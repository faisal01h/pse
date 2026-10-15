<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_records', function (Blueprint $table) {
            $table->string('reliability_level', 32)->nullable()->after('check_legal_label');
        });

        Schema::table('business_affiliations', function (Blueprint $table) {
            $table->string('reliability_level', 32)->nullable()->after('check_legal_label');
        });
    }

    public function down(): void
    {
        Schema::table('public_records', function (Blueprint $table) {
            $table->dropColumn('reliability_level');
        });

        Schema::table('business_affiliations', function (Blueprint $table) {
            $table->dropColumn('reliability_level');
        });
    }
};
