<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->unsignedInteger('samples')->default(1)->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('ideology_positions', function (Blueprint $table) {
            $table->dropColumn('samples');
        });
    }
};
