<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('action', 32)->default('inference')->after('user_id');
            $table->string('subject')->nullable()->after('action');
            $table->nullableMorphs('auditable');
            $table->json('changes')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropMorphs('auditable');
            $table->dropColumn(['action', 'subject', 'changes']);
        });
    }
};
