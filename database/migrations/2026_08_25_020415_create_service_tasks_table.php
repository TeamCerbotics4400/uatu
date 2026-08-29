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
        Schema::create('service_tasks', function (Blueprint $table) {
            $table->id();

            $table->enum('status', [
                'PENDING',
                'ASSIGNED',
                'IN_PROGRESS',
                'BLOCKED',
                'COMPLETED',
                'CANCELLED'
            ])->default('PENDING');

            // Foreign key to `teams` table (BigInteger)
            $table->foreignUuid('assigned_team')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            // Foreign key to `users` table (UUID string)
            $table->foreignUuid('assigned_user')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_tasks');
    }
};