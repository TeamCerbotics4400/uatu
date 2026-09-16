<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class ServiceTask extends Model
{
    protected $fillable = [
        'status',
        'assigned_team',
        'assigned_user_1',
        'assigned_user_2',
        'assigned_user_3',
        'priority',
        'required_service',
        'match_id',
        'started_at',
        'completed_at',
    ];
 
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'PENDING',
    ];
 
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_team');
    }

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_1');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_2');
    }

    public function user3(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_3');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }

    /**
     * Obtiene todos los usuarios asignados a esta tarea
     */
    public function getAssignedUsers(): array
    {
        $users = [];
        
        if ($this->assigned_user_1) {
            $users[] = $this->user1;
        }
        if ($this->assigned_user_2) {
            $users[] = $this->user2;
        }
        if ($this->assigned_user_3) {
            $users[] = $this->user3;
        }

        return $users;
    }

    /**
     * Obtiene los nombres de los usuarios asignados (para display)
     */
    public function getAssignedUserNames(): string
    {
        $users = $this->getAssignedUsers();
        $names = array_map(fn ($user) => $user->name, $users);
        return implode(', ', $names) ?: '—';
    }

    /**
     * Obtiene el label de prioridad para mostrar
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            '1' => 'Priority 1 (Critical)',
            '2' => 'Priority 2 (High)',
            '3' => 'Priority 3 (High-Medium)',
            '4' => 'Priority 4 (Medium)',
            '5' => 'Priority 5 (Medium-Low)',
            '6' => 'Priority 6 (Low)',
            '7' => 'Priority 7 (Very Low)',
            default => 'Unknown',
        };
    }

    /**
     * Badge color según prioridad
     */
    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            '1', '2' => 'danger',      // Rojo
            '3', '4' => 'warning',     // Amarillo
            '5', '6', '7' => 'success', // Verde
            default => 'gray',
        };
    }
}