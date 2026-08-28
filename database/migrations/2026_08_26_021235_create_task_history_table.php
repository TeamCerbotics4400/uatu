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
            $table->string('previous_state')->nullable();
            $table->string('new_state');
            //primero lo declaras  para q no te error
            $table->unsignedBigInteger('service_task_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            
            $table->timestamps();


//van desoues del timestamps 

$table->foreign('service_task_id')->references('id')->on('service_task');
$table->foreign('user_id')->references('id')->on('users');
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
