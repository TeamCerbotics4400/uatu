<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');
            $table->unsignedBigInteger('blue_1')->nullable();
            $table->unsignedBigInteger('blue_2')->nullable();
            $table->unsignedBigInteger('blue_3')->nullable();
            $table->unsignedBigInteger('red_1')->nullable();
            $table->unsignedBigInteger('red_2')->nullable();
            $table->unsignedBigInteger('red_3')->nullable();
            $table->timestamps();

            $table->foreign('blue_1')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('blue_2')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('blue_3')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('red_1')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('red_2')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('red_3')->references('id')->on('teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};