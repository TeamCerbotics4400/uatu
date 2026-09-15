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
            $table->enum('type', ['PIT', 'MATCH', 'CHECKLIST', 'PIT_CHECKLIST']);
            $table->enum('status', ['PENDING', 'IN_PROGRESS', 'BLOCKED', 'DONE', 'CANCELLED'])->default('PENDING');
            
            $table->foreignUuid('assigned_user_1')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignUuid('assigned_user_2')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignUuid('assigned_user_3')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->foreignUuid('assigned_user_4')
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