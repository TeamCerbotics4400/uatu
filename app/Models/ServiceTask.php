<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
 
class ServiceTask extends Model
{
    protected $fillable = [
        'status',
        'assigned_team',
        'assigned_user',
        'match_id',
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

    public function match(): BelongsTo
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }
}