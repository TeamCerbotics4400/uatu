<?php

namespace App\Services;

use App\Models\ServiceTask;
use App\Models\MxTask;
use App\Models\User;
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

        return $task->refresh();
    }

    public function toCancelled(ServiceTask $task): ServiceTask|false
    {
        if (in_array($task->status, ['COMPLETED', 'CANCELLED'])) {
            return false;
        }

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

        return $task->refresh();
    }

    public function toBlocked(ServiceTask $task): ServiceTask|false
    {
        if ($task->status !== 'IN_PROGRESS') {
            return false;
        }

        $task->update(['status' => 'BLOCKED']);

        return $task->refresh();
    }

    public function toPending(ServiceTask $task): ServiceTask|false
    {
        if (!in_array($task->status, ['ASSIGNED', 'PENDING'])) {
            return false;
        }

        $task->update([
            'status' => 'PENDING',
            'assigned_user' => null,
            'started_at' => null,
        ]);

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

    private function userHasActiveTask(int $userId): bool
    {
        $activeStatusesService = ServiceTask::where('assigned_user', $userId)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->exists();
    

        return $activeStatusesService || $activeStatusesMx;
    }
}
