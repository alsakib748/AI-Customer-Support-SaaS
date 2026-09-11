<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AILog extends Model
{
    protected $table = 'ai_logs';

    protected $connection = 'tenant';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'agent',
        'provider',
        'model',
        'request_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'duration_ms',
        'status',
        'error_type',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'duration_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForAgent($query, $agent)
    {
        return $query->where('agent', $agent);
    }
}
