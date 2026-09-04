<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matches extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'blue_1',
        'blue_2',
        'blue_3',
        'red_1',
        'red_2',
        'red_3',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function blue1Team()
    {
        return $this->belongsTo(Team::class, 'blue_1');
    }

    public function blue2Team()
    {
        return $this->belongsTo(Team::class, 'blue_2');
    }

    public function blue3Team()
    {
        return $this->belongsTo(Team::class, 'blue_3');
    }

    public function red1Team()
    {
        return $this->belongsTo(Team::class, 'red_1');
    }

    public function red2Team()
    {
        return $this->belongsTo(Team::class, 'red_2');
    }

    public function red3Team()
    {
        return $this->belongsTo(Team::class, 'red_3');
    }

    public function serviceTasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class, 'match_id');
    }

    /**
     * Obtiene los matches donde juega un equipo específico
     */
    public static function getMatchesForTeam(string $teamId): \Illuminate\Database\Eloquent\Builder
    {
        return self::where('blue_1', $teamId)
            ->orWhere('blue_2', $teamId)
            ->orWhere('blue_3', $teamId)
            ->orWhere('red_1', $teamId)
            ->orWhere('red_2', $teamId)
            ->orWhere('red_3', $teamId);
    }

    /**
     * Obtiene el estado del ServiceTask de un equipo en este match
     * Retorna: 'completed', 'in_progress', o 'pending' (rojo por defecto)
     */
    public function getTeamServiceStatus(?string $teamId): string
    {
        if (!$teamId) {
            return 'pending';
        }

        $serviceTask = ServiceTask::where('match_id', $this->id)
            ->where('assigned_team', $teamId)
            ->first();

        if (!$serviceTask) {
            return 'pending';
        }

        return match ($serviceTask->status) {
            'COMPLETED' => 'completed',
            'IN_PROGRESS' => 'in_progress',
            default => 'pending', // PENDING, ASSIGNED, BLOCKED, CANCELLED
        };
    }
}