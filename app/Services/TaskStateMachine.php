<?php

namespace App\Services;

use App\Models\MxTask;
use App\Models\ServiceTask;
use App\Models\ServiceTaskUser;
use App\Models\User;
use App\Models\TaskHistory;
use Carbon\Carbon;

class TaskStateMachine
{
    // =====================================================
    // SERVICETASK METHODS
    //
    // Cada usuario asignado tiene su propio estado dentro de la tarea
    // (service_task_users.status): ASSIGNED, ACTIVE, SUSPENDED, DONE.
    // El estado de la tarea se deriva de los estados de sus usuarios.
    // Un usuario solo puede estar ACTIVE en una ServiceTask a la vez.
    // =====================================================

    public function hasAssignedUsers(ServiceTask $task): bool
    {
        return $task->assigned_user_1 || $task->assigned_user_2 || $task->assigned_user_3;
    }

    /**
     * Alinea service_task_users con assigned_user_1/2/3 y recalcula el
     * estado de la tarea. Se llama al asignar y al editar desde la dashboard.
     */
    public function syncParticipants(ServiceTask $task): void
    {
        $ids = array_values(array_unique(array_filter([
            $task->assigned_user_1,
            $task->assigned_user_2,
            $task->assigned_user_3,
        ])));

        $existing = $task->participants()->pluck('user_id')->all();

        foreach (array_diff($ids, $existing) as $userId) {
            $task->participants()->create(['user_id' => $userId, 'status' => 'ASSIGNED']);
        }

        $removed = array_diff($existing, $ids);

        if ($removed) {
            $task->participants()->whereIn('user_id', $removed)->delete();

            foreach (User::whereIn('id', $removed)->get() as $user) {
                $this->refreshUserStatus($user);
            }
        }

        $task->unsetRelation('participants');
        $this->refreshTaskStatus($task);
    }

    public function toAssigned(ServiceTask $task): ServiceTask|false
    {
        if ($task->status !== 'PENDING' || !$this->hasAssignedUsers($task)) {
            return false;
        }

        $this->syncParticipants($task);
        $this->recordHistory($task, 'PENDING');

        return $task->refresh();
    }

    /**
     * El usuario empieza (o reanuda) su parte. Falla si ya esta ACTIVE en
     * otra ServiceTask: debe suspenderla primero.
     */
    public function userStart(ServiceTask $task, User $user): ServiceTask|false
    {
        if (in_array($task->status, ['BLOCKED', 'COMPLETED', 'CANCELLED'])) {
            return false;
        }

        $participant = $this->participant($task, $user);

        if (!$participant || !in_array($participant->status, ['ASSIGNED', 'SUSPENDED'])) {
            return false;
        }

        if ($this->activeParticipationFor($user)) {
            return false;
        }

        $previous = $task->status;

        $participant->update([
            'status' => 'ACTIVE',
            'started_at' => $participant->started_at ?? Carbon::now(),
        ]);

        if (!$task->started_at) {
            $task->update(['started_at' => Carbon::now()]);
        }

        $this->refreshTaskStatus($task);
        $this->refreshUserStatus($user);
        $this->recordHistory($task, $previous, $user);

        return $task->refresh();
    }

    /**
     * El usuario suspende su parte para ir a otra tarea. Queda AVAILABLE.
     */
    public function userSuspend(ServiceTask $task, User $user): ServiceTask|false
    {
        $participant = $this->participant($task, $user);

        if (!$participant || $participant->status !== 'ACTIVE') {
            return false;
        }

        $previous = $task->status;
        $participant->update(['status' => 'SUSPENDED']);

        $this->refreshTaskStatus($task);
        $this->refreshUserStatus($user);
        $this->recordHistory($task, $previous, $user);

        return $task->refresh();
    }

    /**
     * El usuario termina su parte. La tarea pasa a COMPLETED solo cuando
     * todos los asignados terminaron.
     */
    public function userComplete(ServiceTask $task, User $user): ServiceTask|false
    {
        if (in_array($task->status, ['COMPLETED', 'CANCELLED'])) {
            return false;
        }

        $participant = $this->participant($task, $user);

        if (!$participant || !in_array($participant->status, ['ACTIVE', 'SUSPENDED'])) {
            return false;
        }

        $previous = $task->status;
        $participant->update(['status' => 'DONE', 'completed_at' => Carbon::now()]);

        $this->refreshTaskStatus($task);
        $this->refreshUserStatus($user);
        $this->recordHistory($task, $previous, $user);

        return $task->refresh();
    }

    public function toCancelled(ServiceTask $task): ServiceTask|false
    {
        if (in_array($task->status, ['COMPLETED', 'CANCELLED'])) {
            return false;
        }

        $previous = $task->status;
        $task->update([
            'status' => 'CANCELLED',
            'completed_at' => Carbon::now(),
        ]);

        $this->refreshParticipantsUsers($task);
        $this->recordHistory($task, $previous);

        return $task->refresh();
    }

    public function toBlocked(ServiceTask $task): ServiceTask|false
    {
        if (!in_array($task->status, ['IN_PROGRESS', 'SUSPENDED'])) {
            return false;
        }

        $previous = $task->status;
        $task->update(['status' => 'BLOCKED']);
        $this->recordHistory($task, $previous);

        return $task->refresh();
    }

    public function toUnblocked(ServiceTask $task): ServiceTask|false
    {
        if ($task->status !== 'BLOCKED') {
            return false;
        }

        $this->refreshTaskStatus($task, ignoreBlocked: true);
        $this->recordHistory($task, 'BLOCKED');

        return $task->refresh();
    }

    public function toPending(ServiceTask $task): ServiceTask|false
    {
        if (!in_array($task->status, ['ASSIGNED', 'PENDING'])) {
            return false;
        }

        $previous = $task->status;
        $task->update([
            'status' => 'PENDING',
            'assigned_user_1' => null,
            'assigned_user_2' => null,
            'assigned_user_3' => null,
            'started_at' => null,
        ]);

        $this->syncParticipants($task);
        $this->recordHistory($task, $previous);

        return $task->refresh();
    }

    public function activeParticipationFor(User $user): ?ServiceTaskUser
    {
        return ServiceTaskUser::where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->with('task.team')
            ->first();
    }

    /**
     * Recalcula users.status a partir de lo que el usuario tiene activo.
     * BUSY si esta ACTIVE en una ServiceTask o en una MxTask abierta;
     * si no, AVAILABLE (RESTING se respeta porque es manual).
     */
    public function refreshUserStatus(?User $user): void
    {
        if (!$user) {
            return;
        }

        $busy = ServiceTaskUser::where('user_id', $user->id)->where('status', 'ACTIVE')->exists()
            || MxTask::whereIn('status', ['PENDING', 'IN_PROGRESS', 'BLOCKED'])
                ->where(function ($query) use ($user) {
                    $query->where('assigned_user_1', $user->id)
                        ->orWhere('assigned_user_2', $user->id)
                        ->orWhere('assigned_user_3', $user->id)
                        ->orWhere('assigned_user_4', $user->id);
                })
                ->exists();

        if ($busy) {
            if ($user->status !== 'BUSY') {
                $user->update(['status' => 'BUSY']);
            }
        } elseif (in_array($user->status, ['BUSY', 'NEEDS_HELP'])) {
            $user->update(['status' => 'AVAILABLE']);
        }
    }

    public function getTaskInfo(ServiceTask $task): array
    {
        return [
            'id' => $task->id,
            'status' => $task->status,
            'assigned_team' => $task->assigned_team,
            'assigned_user_1' => $task->assigned_user_1,
            'assigned_user_2' => $task->assigned_user_2,
            'assigned_user_3' => $task->assigned_user_3,
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

    public function getParticipantElapsedTime(ServiceTaskUser $participant): ?string
    {
        if (!$participant->started_at) {
            return null;
        }

        $endTime = $participant->completed_at ?? Carbon::now();

        return $participant->started_at->diff($endTime)->format('%H:%I:%S');
    }

    private function participant(ServiceTask $task, User $user): ?ServiceTaskUser
    {
        return $task->participants()->where('user_id', $user->id)->first();
    }

    /**
     * Estado de la tarea a partir de sus usuarios:
     * sin usuarios -> PENDING; todos DONE -> COMPLETED; alguien ACTIVE -> IN_PROGRESS;
     * nadie activo pero alguien ya empezo o termino -> SUSPENDED; nadie empezo -> ASSIGNED.
     * BLOCKED y CANCELLED se conservan porque son manuales.
     */
    private function refreshTaskStatus(ServiceTask $task, bool $ignoreBlocked = false): void
    {
        if ($task->status === 'CANCELLED') {
            return;
        }

        $statuses = $task->participants()->pluck('status');

        if ($statuses->isEmpty()) {
            $new = 'PENDING';
        } elseif ($statuses->every(fn ($s) => $s === 'DONE')) {
            $new = 'COMPLETED';
        } elseif ($task->status === 'BLOCKED' && !$ignoreBlocked) {
            $new = 'BLOCKED';
        } elseif ($statuses->contains('ACTIVE')) {
            $new = 'IN_PROGRESS';
        } elseif ($statuses->contains('SUSPENDED') || $statuses->contains('DONE')) {
            $new = 'SUSPENDED';
        } else {
            $new = 'ASSIGNED';
        }

        $data = ['status' => $new];

        if ($new === 'COMPLETED') {
            $data['completed_at'] = $task->completed_at ?? Carbon::now();
        } elseif ($task->completed_at) {
            $data['completed_at'] = null;
        }

        if ($new !== $task->status || ($data['completed_at'] ?? null) !== $task->completed_at) {
            $task->update($data);
        }
    }

    private function refreshParticipantsUsers(ServiceTask $task): void
    {
        foreach ($task->participants()->with('user')->get() as $participant) {
            $this->refreshUserStatus($participant->user);
        }
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

    private function releaseMxTaskUsers(MxTask $task): void
    {
        $userIds = array_filter([
            $task->assigned_user_1,
            $task->assigned_user_2,
            $task->assigned_user_3,
            $task->assigned_user_4,
        ]);

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->refreshUserStatus($user);
        }
    }

    private function recordHistory(ServiceTask $task, string $previousState, ?User $actor = null): void
    {
        TaskHistory::create([
            'service_task_id' => $task->id,
            'previous_state' => $previousState,
            'new_state' => $task->status,
            'user_id' => $actor?->id ?? $task->assigned_user_1 ?? $task->assigned_user_2 ?? $task->assigned_user_3,
        ]);
    }
}
