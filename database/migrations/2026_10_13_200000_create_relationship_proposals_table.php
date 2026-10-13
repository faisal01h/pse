<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('relationship_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_record_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('fingerprint', 191);
            $table->json('payload');
            $table->text('excerpt');
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->unique(['public_record_id', 'fingerprint']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('relationship_proposals');
    }
};
