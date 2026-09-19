<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'status',
        'is_admin',
        'phone_number',
        'telegram_chat_id',
        'telegram_username',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene las ServiceTasks donde este usuario
     * está asignado en cualquiera de los 3 campos.
     */
    public function serviceTasks()
    {
        return ServiceTask::where(function ($query) {
            $query->where('assigned_user_1', $this->id)
                  ->orWhere('assigned_user_2', $this->id)
                  ->orWhere('assigned_user_3', $this->id);
        });
    }

    public function mxTasks(): HasMany
    {
        return $this->hasMany(MXTask::class, 'assigned_user');
    }

    public function currentTask()
    {
        return $this->serviceTasks()
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->first();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'AVAILABLE' && !$this->currentTask();
    }

    public function serviceParticipations(): HasMany
    {
        return $this->hasMany(ServiceTaskUser::class, 'user_id');
    }

    public function activeServiceParticipation(): ?ServiceTaskUser
    {
        return $this->serviceParticipations()->where('status', 'ACTIVE')->with('task.team')->first();
    }

    public function telegramMessages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'user_id');
    }

    public function canReceiveTelegram(): bool
    {
        return !empty($this->telegram_chat_id);
    }

    public function getCurrentTaskDisplay(): string
    {
        $participation = $this->activeServiceParticipation();

        if ($participation) {
            $teamName = $participation->task?->team?->name ?? 'UNKNOWN';
            return 'HELPING_' . strtoupper($teamName);
        }

        $suspended = $this->serviceParticipations()->where('status', 'SUSPENDED')->with('task.team')->first();

        if ($suspended) {
            $teamName = $suspended->task?->team?->name ?? 'UNKNOWN';
            return 'SUSPENDED_' . strtoupper($teamName);
        }

        // Buscar MxTask activo (en cualquiera de los 4 campos)
        $mxTask = MxTask::whereIn('status', ['PENDING', 'IN_PROGRESS'])
            ->where(function ($query) {
                $query->where('assigned_user_1', $this->id)
                      ->orWhere('assigned_user_2', $this->id)
                      ->orWhere('assigned_user_3', $this->id)
                      ->orWhere('assigned_user_4', $this->id);
            })
            ->first();

        if ($mxTask) {
            return $mxTask->type;
        }

        return '—';
    }
}

