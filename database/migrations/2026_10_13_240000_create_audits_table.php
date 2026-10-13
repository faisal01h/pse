<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('public_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trigger', 16);
            $table->string('status', 16);
            $table->unsignedInteger('proposals_created')->default(0);
            $table->string('message')->nullable();
            $table->timestamps();
            $table->index(['public_record_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
