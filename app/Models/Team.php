<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class Team extends Model
{
    use HasUuids;
 
    protected $keyType = 'string';
    public $incrementing = false;
 
    protected $fillable = [
        'name',
        'current_service_status',
    ];
 
    public function serviceTasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class, 'assigned_team');
    }
 
    public function activeTasks(): HasMany
    {
        return $this->serviceTasks()
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED']);
    }
 
    public function completedTasks(): HasMany
    {
        return $this->serviceTasks()
            ->where('status', 'COMPLETED');
    }

    /**
     * Obtiene la prioridad más alta (número más bajo) de las tareas activas del equipo
     */
    public function getHighestActivePriority(): ?string
    {
        return $this->activeTasks()
            ->orderBy('priority')
            ->value('priority');
    }

    /**
     * Calcula el service status del equipo basado en sus tareas
     * Retorna: 'IN_PROGRESS' | 'DONE' | 'NOT_HELPED' | current status si es 'PAUSE'
     * 
     * Lógica:
     * - Si hay tareas ASSIGNED, IN_PROGRESS, BLOCKED → IN_PROGRESS
     * - Si hay tareas COMPLETED (pero ninguna activa) → DONE
     * - Si no hay tareas de esos tipos → NOT_HELPED
     * - Si el estado actual es PAUSE → se mantiene (es manual)
     */
    public function calculateServiceStatus(): string
    {
        // Si está en PAUSE, se mantiene (es un estado manual)
        if ($this->current_service_status === 'PAUSE') {
            return 'PAUSE';
        }

        // Si serviceTasks está eager loaded, usa eso en memoria
        if ($this->relationLoaded('serviceTasks')) {
            $allTasks = $this->serviceTasks
                ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED', 'COMPLETED', 'CANCELLED'])
                ->values();
        } else {
            // Si no, hace query fresca
            $allTasks = $this->serviceTasks()
                ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED', 'COMPLETED', 'CANCELLED'])
                ->get();
        }

        // Excluir tareas canceladas
        $allTasks = $allTasks->where('status', '!=', 'CANCELLED');

        if ($allTasks->isEmpty()) {
            return 'NOT_HELPED';
        }

        // Si hay tareas activas (no completadas) → IN_PROGRESS
        $hasActiveTasks = $allTasks->contains(fn ($task) => 
            in_array($task->status, ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED'])
        );

        if ($hasActiveTasks) {
            return 'IN_PROGRESS';
        }

        // Si solo hay tareas completadas → DONE
        $hasCompletedTasks = $allTasks->contains(fn ($task) => 
            $task->status === 'COMPLETED'
        );

        if ($hasCompletedTasks) {
            return 'DONE';
        }

        return 'NOT_HELPED';
    }

    /**
     * Obtiene los required_service de las tareas activas Y completadas del equipo
     * Retorna: string con servicios únicos separados por comas (ej: "MECHANICAL, PROGRAMMING")
     * 
     * Incluye tareas en estado:
     * - ASSIGNED, IN_PROGRESS, BLOCKED (activas)
     * - COMPLETED (completadas)
     * 
     * Excluye tareas CANCELLED
     */
    public function getActiveRequiredServices(): string
    {
        // Si serviceTasks está eager loaded, usa eso en memoria
        if ($this->relationLoaded('serviceTasks')) {
            $allTasks = $this->serviceTasks
                ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED', 'COMPLETED'])
                ->values();
        } else {
            // Si no, hace query fresca
            $allTasks = $this->serviceTasks()
                ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'SUSPENDED', 'BLOCKED', 'COMPLETED'])
                ->get();
        }

        // Filtra servicios que no sean NONE y obtiene valores únicos
        $services = $allTasks
            ->where('required_service', '!=', 'NONE')
            ->pluck('required_service')
            ->unique()
            ->toArray();

        if (empty($services)) {
            return 'NONE';
        }

        return implode(', ', $services);
    }
}