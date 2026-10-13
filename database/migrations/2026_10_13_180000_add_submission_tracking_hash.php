<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public_records') || Schema::hasColumn('public_records', 'tracking_code_hash')) {
            return;
        }

        Schema::table('public_records', function (Blueprint $table) {
            $table->char('tracking_code_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('public_records') || ! Schema::hasColumn('public_records', 'tracking_code_hash')) {
            return;
        }

        Schema::table('public_records', function (Blueprint $table) {
            $table->dropUnique(['tracking_code_hash']);
            $table->dropColumn('tracking_code_hash');
        });
    }
};
