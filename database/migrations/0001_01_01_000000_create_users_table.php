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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('role', ['STUDENT', 'MENTOR'])->default('STUDENT');
            $table->enum('serviceType', ['MECHANICAL', 'PROGRAMMING', 'ALL', 'STRATEGIST'])->default('ALL');
            $table->enum('status', ['AVAILABLE', 'BUSY', 'RESTING'])->default('AVAILABLE');
        });
    }


};
