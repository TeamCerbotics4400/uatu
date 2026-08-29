<?php

namespace App\Services;

use App\Models\MxTask;
use App\Models\ServiceTask;
use App\Models\User;
use App\Models\TaskHistory;
use Carbon\Carbon;

class TaskStateMachine
{
    public function toAssigned(ServiceTask $task, User $user): ServiceTask|false
    {
        if ($task->status !== 'PENDING') {
            return false;
        }

        if ($this->userHasActiveTask($user->id)) {
            return false;
        }

        $task->update([
            'status' => 'ASSIGNED',
            'assigned_user' => $user->id,
        ]);

        $user->update(['status' => 'BUSY']);
        $this->recordHistory($task, 'PENDING');

        return $task->refresh();
    }

    public function toInProgress(ServiceTask $task): ServiceTask|false
    {
        if ($task->status !== 'ASSIGNED') {
            return false;
        }

        $task->update([
            'status' => 'IN_PROGRESS',
            'started_at' => Carbon::now(),
        ]);
        $this->recordHistory($task, 'ASSIGNED');

        return $task->refresh();
    }

    public function toCompleted(ServiceTask $task): ServiceTask|false
    {
        if ($task->status !== 'IN_PROGRESS') {
            return false;
        }

        $task->update([
            'status' => 'COMPLETED',
            'completed_at' => Carbon::now(),
        ]);

        if ($task->assigned_user) {
            $user = User::find($task->assigned_user);
            if ($user) {
                $user->update(['status' => 'AVAILABLE']);
            }
        }
        $this->recordHistory($task, 'IN_PROGRESS');

        return $task->refresh();
    }

    public function toCancelled(ServiceTask $task): ServiceTask|false
    {
        if (in_array($task->status, ['COMPLETED', 'CANCELLED'])) {
            return false;
        }

        $previousState = $task->status;
        $task->update([
            'status' => 'CANCELLED',
            'completed_at' => Carbon::now(),
        ]);

        if ($task->assigned_user) {
            $user = User::find($task->assigned_user);
            if ($user) {
                $user->update(['status' => 'AVAILABLE']);
            }
        }
        $this->recordHistory($task, $previousState);

        return $task->refresh();
    }

    public function toBlocked(ServiceTask $task): ServiceTask|false
    {
        if ($task->status !== 'IN_PROGRESS') {
            return false;
        }

        $task->update(['status' => 'BLOCKED']);
        $this->recordHistory($task, 'IN_PROGRESS');

        return $task->refresh();
    }

    public function toPending(ServiceTask $task): ServiceTask|false
    {
        if (!in_array($task->status, ['ASSIGNED', 'PENDING'])) {
            return false;
        }

        $previousState = $task->status;
        $task->update([
            'status' => 'PENDING',
            'assigned_user' => null,
            'started_at' => null,
        ]);
        $this->recordHistory($task, $previousState);

        return $task->refresh();
    }

    public function getTaskInfo(ServiceTask $task): array
    {
        return [
            'id' => $task->id,
            'status' => $task->status,
            'assigned_team' => $task->assigned_team,
            'assigned_user' => $task->assigned_user,
            'started_at' => $task->started_at,
            'completed_at' => $task->completed_at,
            'elapsed_time' => $this->getElapsedTime($task),
        ];
    }

    public function getElapsedTime(ServiceTask $task): ?string
    {
        if (!$task->started_at) {
            return null;
        }

        $endTime = $task->completed_at ?? Carbon::now();
        $diff = $task->started_at->diff($endTime);

        return $diff->format('%H:%I:%S');
    }

    private function userHasActiveTask(string $userId): bool
    {
        $activeStatusesService = ServiceTask::where('assigned_user', $userId)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->exists();

        $activeStatusesMx = MxTask::where('assigned_user', $userId)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->exists();

        return $activeStatusesService || $activeStatusesMx;
    }

    private function recordHistory(ServiceTask $task, string $previousState): void
    {
        TaskHistory::create([
            'service_task_id' => $task->id,
            'previous_state' => $previousState,
            'new_state' => $task->status,
            'user_id' => $task->assigned_user, // User who is currently assigned (can be null)
        ]);
    }
}