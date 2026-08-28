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
        Schema::create('task_history', function (Blueprint $table) {
            $table->id();
            $table->foreign('service_task_id')->constrained('service_task')->cascadeOnDelete();
            $table->foreign('user_id')->nullable()->constrained('users');
            $table->string('previous_state')->nullable();
            $table->string('new_state');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_history');
    }
};
