<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class MxTask extends Model
{
    protected $table = 'mxtasks';
    
    protected $fillable = [
        'type',
        'status',
        'assigned_user_1',
        'assigned_user_2',
        'assigned_user_3',
        'assigned_user_4',
        'started_at',
        'completed_at',
    ];
 
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
 
    public function assignedUser1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_1');
    }

    public function assignedUser2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_2');
    }

    public function assignedUser3(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_3');
    }

    public function assignedUser4(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_4');
    }
}