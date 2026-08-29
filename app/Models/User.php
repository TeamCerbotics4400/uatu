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
}