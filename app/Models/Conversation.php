<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    protected $fillable = [
        'conversation_id',
        'channel',
        'direction',
        'last_event',
        'patient_id',
        'disconnection_reason',
        'summary',
        'user_sentiment',
        'successful',
        'transferred',
        'duration_ms',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'transferred' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
