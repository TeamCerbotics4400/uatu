<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MxTask extends Model
{
    use HasFactory;

    protected $table = 'mxtasks';

    protected $fillable = [
        'type',
        'status',
        'assigned_user',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'type' => 'string',
        'status' => 'string',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}