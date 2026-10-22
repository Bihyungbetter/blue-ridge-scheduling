<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'patient_id',
        'department',
        'appointment_type',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'conversation_id',
        'idempotency_key',
        'cancelled_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_SCHEDULED);
    }

    public function departmentName(): string
    {
        return config("scheduling.departments.{$this->department}.name", $this->department);
    }

    public function typeLabel(): string
    {
        return config("scheduling.departments.{$this->department}.appointment_types.{$this->appointment_type}.label", $this->appointment_type);
    }
}
