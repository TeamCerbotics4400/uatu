<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_task_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_task_id')->constrained('service_tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['ASSIGNED', 'ACTIVE', 'SUSPENDED', 'DONE'])->default('ASSIGNED');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['service_task_id', 'user_id']);
        });

        DB::statement('ALTER TABLE service_tasks DROP CONSTRAINT IF EXISTS service_tasks_status_check');
        DB::statement("ALTER TABLE service_tasks ADD CONSTRAINT service_tasks_status_check CHECK (status::text = ANY (ARRAY['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED', 'COMPLETED', 'CANCELLED']::text[]))");

        $now = now();

        foreach (DB::table('service_tasks')->get() as $task) {
            $participantStatus = match ($task->status) {
                'COMPLETED' => 'DONE',
                'IN_PROGRESS', 'BLOCKED' => 'ACTIVE',
                default => 'ASSIGNED',
            };

            foreach (array_unique(array_filter([$task->assigned_user_1, $task->assigned_user_2, $task->assigned_user_3])) as $userId) {
                DB::table('service_task_users')->insert([
                    'service_task_id' => $task->id,
                    'user_id' => $userId,
                    'status' => $participantStatus,
                    'started_at' => $participantStatus === 'ASSIGNED' ? null : $task->started_at,
                    'completed_at' => $participantStatus === 'DONE' ? $task->completed_at : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_task_users');

        DB::statement('ALTER TABLE service_tasks DROP CONSTRAINT IF EXISTS service_tasks_status_check');
        DB::statement("ALTER TABLE service_tasks ADD CONSTRAINT service_tasks_status_check CHECK (status::text = ANY (ARRAY['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'BLOCKED', 'COMPLETED', 'CANCELLED']::text[]))");
    }
};
