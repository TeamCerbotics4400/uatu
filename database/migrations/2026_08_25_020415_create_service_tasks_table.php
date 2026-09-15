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

            // Foreign key to `teams` table (UUID)
            $table->foreignUuid('assigned_team')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            // Priority: 1 (highest) to 7 (lowest)
            $table->enum('priority', ['1', '2', '3', '4', '5', '6', '7'])
                ->default('4')
                ->comment('Priority level: 1 (critical) to 7 (very low)');

            // Required service type for this task
            $table->enum('required_service', ['MECHANICAL', 'PROGRAMMING', 'BOTH', 'NONE'])
                ->default('NONE')
                ->comment('Type of service required for this task');

            // Foreign key to `matches` table (BigInteger)
            $table->unsignedBigInteger('match_id')
                ->nullable()
                ->constrained('matches')
                ->nullOnDelete();

            // Foreign key to `users` table (UUID)
            $table->foreignUuid('assigned_user')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('priority');
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