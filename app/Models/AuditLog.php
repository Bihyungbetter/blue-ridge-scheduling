<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'action',
        'actor_type',
        'user_id',
        'conversation_id',
        'patient_id',
        'appointment_id',
        'outcome',
    ];

    /**
     * Record an action. Pass IDs only; never names, birth dates or transcripts.
     */
    public static function record(string $action, string $actorType, string $outcome = 'ok', array $ids = []): self
    {
        return static::create([
            'action' => $action,
            'actor_type' => $actorType,
            'outcome' => $outcome,
            ...array_intersect_key($ids, array_flip(['user_id', 'conversation_id', 'patient_id', 'appointment_id'])),
        ]);
    }
}
