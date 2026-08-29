<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class MXTask extends Model
{
    use HasUuids;
 
    protected $table = 'mx_tasks';
    protected $keyType = 'string';
    public $incrementing = false;
 
    protected $fillable = [
        'type',
        'status',
        'assigned_user',
        'assigned_team',
        'started_at',
        'completed_at',
    ];
 
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
 
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_team');
    }
 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user');
    }
}