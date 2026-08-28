<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTask extends Model
{
    use HasFactory;

    protected $table = 'service_tasks';

    protected $fillable = [
        'status',
        'assigned_team',
        'assigned_user',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => 'string',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function team()
{
    return $this->belongsTo(Team::class, 'assigned_team');
}

public function user()
{
    return $this->belongsTo(User::class, 'assigned_user');
}
}