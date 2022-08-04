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
        Schema::create('psesupporters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pictureUrl')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('quoted_statement')->nullable();
            $table->string('party')->nullable();
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
