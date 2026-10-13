<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('parties', 'logo_path')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->string('logo_path')->nullable()->after('name');
            });
        }

        if (! Schema::hasTable('record_attachments')) {
            Schema::create('record_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('public_record_id')->constrained('public_records')->cascadeOnDelete();
                $table->string('path');
                $table->string('original_name');
                $table->string('mime', 127);
                $table->unsignedInteger('size');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('record_attachments');

        if (Schema::hasColumn('parties', 'logo_path')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
