<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class Team extends Model
{
    use HasUuids;
 
    protected $keyType = 'string';
    public $incrementing = false;
 
    protected $fillable = [
        'name',
        'priority',
        'required_service',
        'current_service_status',
    ];
 
    public function serviceTasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class, 'assigned_team');
    }
 
    public function activeTasks(): HasMany
    {
        return $this->serviceTasks()
            ->whereIn('status', ['ASSIGNED', 'IN_PROGRESS', 'BLOCKED']);
    }
 
    public function completedTasks(): HasMany
    {
        return $this->serviceTasks()
            ->where('status', 'COMPLETED');
    }
}