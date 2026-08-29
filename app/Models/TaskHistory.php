<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskHistory extends Model
{
    

    protected $table = 'task_history';

    protected $fillable = [
        'service_task_id',
        'user_id',
        'previous_state',
        'new_state',
        
    ];

    protected $casts = [
        
    ];

}
