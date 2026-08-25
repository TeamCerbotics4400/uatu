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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('priority', ['1', '2', '3', '4', '5', '6', '7']);
            $table->enum('required_service', ['MECHANICAL', 'PROGRAMMING', 'BOTH', 'NONE']);
            $table->enum('current_service_status', ['IN_PROGRESS', 'DONE', 'NOT_HELPED', 'PAUSE'])->default('NOT_HELPED');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};