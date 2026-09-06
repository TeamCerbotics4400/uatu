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
        'phone_number'
        
    ];

       protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
 
    public function serviceTasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class, 'assigned_user');
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

    

    public function getCurrentTaskDisplay(): string
{
    // Buscar ServiceTask activo
    $serviceTask = $this->serviceTasks()
        ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
        ->with('team')
        ->first();

    if ($serviceTask) {
        $teamName = $serviceTask->team?->name ?? 'UNKNOWN';
        return 'HELPING_' . strtoupper($teamName);
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