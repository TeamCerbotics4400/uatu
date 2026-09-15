<?php

namespace App\Services;

use App\Models\MxTask;
use App\Models\ServiceTask;
use App\Models\User;
use App\Models\TaskHistory;
use Carbon\Carbon;

class TaskStateMachine
{
    // =====================================================
    // SERVICETASK METHODS
    // =====================================================

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

    // =====================================================
    // MXTASK METHODS
    // =====================================================

    /**
     * MxTask: PENDING → IN_PROGRESS
     * Automáticamente asigna started_at
     */
    public function mxStart(MxTask $task): MxTask|false
    {
        if ($task->status !== 'PENDING') {
            return false;
        }

        $task->update([
            'status' => 'IN_PROGRESS',
            'started_at' => Carbon::now(),
        ]);

        return $task->refresh();
    }

    /**
     * MxTask: IN_PROGRESS → DONE
     * Automáticamente asigna completed_at
     * Marca los 4 usuarios como AVAILABLE
     */
    public function mxComplete(MxTask $task): MxTask|false
    {
        if (!in_array($task->status, ['IN_PROGRESS', 'BLOCKED'])) {
            return false;
        }

        $previousState = $task->status;
        $task->update([
            'status' => 'DONE',
            'completed_at' => Carbon::now(),
        ]);

        $this->releaseMxTaskUsers($task);

        return $task->refresh();
    }

    /**
     * MxTask: IN_PROGRESS → BLOCKED
     */
    public function mxBlock(MxTask $task): MxTask|false
    {
        if ($task->status !== 'IN_PROGRESS') {
            return false;
        }

        $task->update(['status' => 'BLOCKED']);

        return $task->refresh();
    }

    /**
     * MxTask: BLOCKED → IN_PROGRESS
     */
    public function mxUnblock(MxTask $task): MxTask|false
    {
        if ($task->status !== 'BLOCKED') {
            return false;
        }

        $task->update(['status' => 'IN_PROGRESS']);

        return $task->refresh();
    }

    /**
     * MxTask: Cualquier estado → CANCELLED
     * Automáticamente asigna completed_at
     * Marca los 4 usuarios como AVAILABLE
     */
    public function mxCancel(MxTask $task): MxTask|false
    {
        if (in_array($task->status, ['DONE', 'CANCELLED'])) {
            return false;
        }

        $task->update([
            'status' => 'CANCELLED',
            'completed_at' => Carbon::now(),
        ]);

        $this->releaseMxTaskUsers($task);

        return $task->refresh();
    }

    /**
     * MxTask: Obtiene información completa
     */
    public function getMxTaskInfo(MxTask $task): array
    {
        return [
            'id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'assigned_user_1' => $task->assigned_user_1,
            'assigned_user_2' => $task->assigned_user_2,
            'assigned_user_3' => $task->assigned_user_3,
            'assigned_user_4' => $task->assigned_user_4,
            'started_at' => $task->started_at,
            'completed_at' => $task->completed_at,
            'elapsed_time' => $this->getMxTaskElapsedTime($task),
        ];
    }

    /**
     * MxTask: Calcula tiempo transcurrido
     */
    public function getMxTaskElapsedTime(MxTask $task): ?string
    {
        if (!$task->started_at) {
            return null;
        }

        $endTime = $task->completed_at ?? Carbon::now();
        $diff = $task->started_at->diff($endTime);

        return $diff->format('%H:%I:%S');
    }

    // =====================================================
    // PRIVATE HELPER METHODS
    // =====================================================

    private function userHasActiveTask(string $userId): bool
    {
        $activeStatusesService = ServiceTask::where('assigned_user', $userId)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->exists();

        $activeStatusesMx = MxTask::whereIn('status', ['IN_PROGRESS'])
            ->where(function ($query) use ($userId) {
                $query->where('assigned_user_1', $userId)
                      ->orWhere('assigned_user_2', $userId)
                      ->orWhere('assigned_user_3', $userId)
                      ->orWhere('assigned_user_4', $userId);
            })
            ->exists();

        return $activeStatusesService || $activeStatusesMx;
    }

    /**
     * Libera los 4 usuarios de una MxTask (los marca como AVAILABLE)
     */
    private function releaseMxTaskUsers(MxTask $task): void
    {
        $userIds = [
            $task->assigned_user_1,
            $task->assigned_user_2,
            $task->assigned_user_3,
            $task->assigned_user_4,
        ];

        foreach ($userIds as $userId) {
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->update(['status' => 'AVAILABLE']);
                }
            }
        }
    }

    private function recordHistory(ServiceTask $task, string $previousState): void
    {
        TaskHistory::create([
            'service_task_id' => $task->id,
            'previous_state' => $previousState,
            'new_state' => $task->status,
            'user_id' => $task->assigned_user,
        ]);
    }
}