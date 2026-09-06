<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $table = 'telegram_messages';

    protected $fillable = [
        'user_id',
        'chat_id',
        'status',
        'telegram_message_id',
        'body',
        'payload',
        'response',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
