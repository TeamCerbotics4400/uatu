<?php

use App\Models\User;
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
        Schema::create('mxtasks', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['PIT', 'MATCH']);
            $table->enum('status', ['IN_PROGRESS']);
            
            // Replaces unsignedBigInteger and foreign() call
            $table->foreignId('assigned_user')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mxtasks');
    }
};