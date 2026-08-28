<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'blue_1',
        'blue_2',
        'blue_3',
        'red_1',
        'red_2',
        'red_3',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function blue1Team()
{
    return $this->belongsTo(Team::class, 'blue_1');
}

public function blue2Team()
{
    return $this->belongsTo(Team::class, 'blue_2');
}

public function blue3Team()
{
    return $this->belongsTo(Team::class, 'blue_3');
}

public function red1Team()
{
    return $this->belongsTo(Team::class, 'red_1');
}

public function red2Team()
{
    return $this->belongsTo(Team::class, 'red_2');
}

public function red3Team()
{
    return $this->belongsTo(Team::class, 'red_3');
}
}