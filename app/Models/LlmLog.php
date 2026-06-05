<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LlmLog extends Model
{
    protected $table = 'llm_logs';

    protected $fillable = [
        'session_id',
        'provider',
        'model',
        'message_type',
        'content',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'content' => 'array',
        'duration_ms' => 'integer',
    ];
}
