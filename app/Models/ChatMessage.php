<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'tool_calls',
    ];

    protected $casts = [
        'tool_calls' => 'array',
    ];

    /**
     * Get the chat session that owns this message.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
