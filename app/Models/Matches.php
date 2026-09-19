<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matches extends Model
{
    protected $fillable = [
        'number',
        'blue_1',
        'blue_2',
        'blue_3',
        'red_1',
        'red_2',
        'red_3',
    ];

    public function serviceTasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class, 'match_id');
    }

    /**
     * Obtiene los matches que participan los equipos
     */
    public static function getMatchesForTeam(string $teamId)
    {
        return self::where(function ($query) use ($teamId) {
            $query->where('blue_1', $teamId)
                  ->orWhere('blue_2', $teamId)
                  ->orWhere('blue_3', $teamId)
                  ->orWhere('red_1', $teamId)
                  ->orWhere('red_2', $teamId)
                  ->orWhere('red_3', $teamId);
        });
    }

    /**
     * Obtiene el estado de servicio de un equipo en un match específico
     * Retorna: 'completed' | 'in_progress' | 'pending'
     * 
     * Color mapping:
     * - 'pending' → rojo (danger)
     * - 'in_progress' → amarillo (warning)
     * - 'completed' → verde (success)
     */
    public static function getTeamServiceStatus(?string $teamId): string
    {
        if (!$teamId) {
            return 'pending';
        }

        $tasks = ServiceTask::where('assigned_team', $teamId)
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED', 'COMPLETED'])
            ->get();

        if ($tasks->isEmpty()) {
            return 'pending';
        }

        // Si todas las tareas están completadas
        if ($tasks->every(fn ($task) => $task->status === 'COMPLETED')) {
            return 'completed';
        }

        // Si hay al menos una tarea activa (ASSIGNED, IN_PROGRESS, BLOCKED)
        if ($tasks->contains(fn ($task) => in_array($task->status, ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED']))) {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * Obtiene las tareas de servicio de un equipo en este match, ordenadas por prioridad
     */
    public function getTeamTasksByPriority(string $teamId)
    {
        return $this->serviceTasks()
            ->where('assigned_team', $teamId)
            ->orderBy('priority')
            ->get();
    }
}