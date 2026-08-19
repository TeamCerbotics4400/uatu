<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tasks extends Model
{
    protected $fillable = [
        'type',
        'status',
        'priority',
        'assignedUser',
        'team',
        'match',
    ];

    protected $casts = [
        'createdAt'

        => 'datetime',
        'startedAt' => 'datetime',
        'completedAt' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'assignedUser');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team');
    }
}
