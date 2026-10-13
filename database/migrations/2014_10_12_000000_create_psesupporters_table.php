<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
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
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('politicians');
        Schema::dropIfExists('parties');
    }
};
