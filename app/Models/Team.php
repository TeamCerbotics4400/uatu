<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'priority',
        'required_service',
        'current_service_status',
    ];

    protected $casts = [
        'priority' => 'string',
        'required_service' => 'string',
        'current_service_status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}