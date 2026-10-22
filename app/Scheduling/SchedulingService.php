<?php

namespace App\Scheduling;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;

class SchedulingService
{
    public function timezone(): string
    {
        return config('scheduling.timezone');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Parse a clinic-local "YYYY-MM-DDTHH:MM" (or "YYYY-MM-DD HH:MM") string.
     */
    public function parseLocal(string $value): CarbonImmutable
    {
        $value = str_replace(' ', 'T', trim($value));

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $parsed = $this->tryFormat($format, $value);

            if ($parsed !== null && $parsed->format($format) === $value) {
                return $parsed->second(0);
            }
        }

        throw new SchedulingException('That time was not in a format I understand.');
    }

    public function parseDate(string $value): CarbonImmutable
    {
        $value = trim($value);
        $parsed = $this->tryFormat('!Y-m-d', $value);

        if ($parsed === null || $parsed->format('Y-m-d') !== $value) {
            throw new SchedulingException('That date was not in a format I understand.');
        }

        return $parsed;
    }

    private function tryFormat(string $format, string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat($format, $value, $this->timezone()) ?: null;
        } catch (InvalidFormatException) {
            return null;
        }
    }

    public function department(string $key): array
    {
        $department = config("scheduling.departments.{$key}");

        if (! is_array($department)) {
            throw new SchedulingException('That department is not available for scheduling.');
        }

        return $department;
    }

    public function appointmentType(string $department, string $type): array
    {
        $types = $this->department($department)['appointment_types'];

        if (! isset($types[$type])) {
            throw new SchedulingException('That appointment type is not offered in this department.');
        }

        return $types[$type];
    }

    /**
     * Every open start time for one day, in clinic-local time.
     *
     * @return list<CarbonImmutable>
     */
    public function availableSlotsOn(string $department, string $type, CarbonImmutable $date, ?int $ignoreAppointmentId = null): array
    {
        $dept = $this->department($department);
        $minutes = $this->appointmentType($department, $type)['minutes'];
        $day = $date->setTimezone($this->timezone())->startOfDay();

        $hours = $dept['hours'][$day->dayOfWeekIso] ?? null;

        if ($hours === null || in_array($day->format('Y-m-d'), config('scheduling.holidays', []), true)) {
            return [];
        }

        $earliest = $this->now()->addMinutes(config('scheduling.lead_time_minutes'));
        $latestDay = $this->now()->startOfDay()->addDays(config('scheduling.horizon_days'));

        if ($day->greaterThan($latestDay)) {
            return [];
        }

        $open = $day->setTimeFromTimeString($hours[0]);
        $close = $day->setTimeFromTimeString($hours[1]);

        $booked = Appointment::active()
            ->where('department', $department)
            ->where('starts_at', '<', $close->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $open->format('Y-m-d H:i:s'))
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->get(['starts_at', 'ends_at']);

        $slots = [];

        for ($start = $open; $start->addMinutes($minutes)->lessThanOrEqualTo($close); $start = $start->addMinutes($dept['slot_minutes'])) {
            if ($start->lessThan($earliest)) {
                continue;
            }

            $end = $start->addMinutes($minutes);
            $overlapping = $booked->filter(fn ($a) => $a->starts_at->lessThan($end) && $a->ends_at->greaterThan($start))->count();

            if ($overlapping < $dept['capacity']) {
                $slots[] = $start;
            }
        }

        return $slots;
    }

    /**
     * The next few open slots on or after a date.
     *
     * @return list<CarbonImmutable>
     */
    public function nextAvailableSlots(string $department, string $type, ?CarbonImmutable $from = null, ?int $limit = null): array
    {
        $limit ??= config('scheduling.max_slots_returned');
        $day = ($from ?? $this->now())->startOfDay();
        $lastDay = $this->now()->startOfDay()->addDays(config('scheduling.horizon_days'));
        $found = [];

        for (; $day->lessThanOrEqualTo($lastDay) && count($found) < $limit; $day = $day->addDay()) {
            foreach ($this->availableSlotsOn($department, $type, $day) as $slot) {
                $found[] = $slot;

                if (count($found) === $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    public function isBookable(string $department, string $type, CarbonImmutable $start, ?int $ignoreAppointmentId = null): bool
    {
        foreach ($this->availableSlotsOn($department, $type, $start, $ignoreAppointmentId) as $slot) {
            if ($slot->equalTo($start)) {
                return true;
            }
        }

        return false;
    }

    public function book(
        Patient $patient,
        string $department,
        string $type,
        CarbonImmutable $start,
        string $source,
        ?string $conversationId = null,
        ?string $idempotencyKey = null,
    ): Appointment {
        if ($idempotencyKey !== null) {
            $existing = Appointment::where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $minutes = $this->appointmentType($department, $type)['minutes'];

        // SQLite serialises writers, so the re-check inside the transaction is
        // enough. On MySQL/Postgres, add a lock (e.g. lockForUpdate on a
        // per-department row) to stop two callers taking the last seat.
        return DB::transaction(function () use ($patient, $department, $type, $start, $source, $conversationId, $idempotencyKey, $minutes) {
            if (! $this->isBookable($department, $type, $start)) {
                throw new SchedulingException('That time is no longer available.');
            }

            return Appointment::create([
                'patient_id' => $patient->id,
                'department' => $department,
                'appointment_type' => $type,
                'starts_at' => $start,
                'ends_at' => $start->addMinutes($minutes),
                'status' => Appointment::STATUS_SCHEDULED,
                'source' => $source,
                'conversation_id' => $conversationId,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * Whether a patient (not staff) may still change this appointment.
     */
    public function patientMayChange(Appointment $appointment): bool
    {
        return $appointment->status === Appointment::STATUS_SCHEDULED
            && $appointment->starts_at->greaterThan($this->now()->addHours(config('scheduling.change_cutoff_hours')));
    }

    public function cancel(Appointment $appointment): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw new SchedulingException('That appointment is not active.');
        }

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $appointment;
    }

    public function reschedule(Appointment $appointment, CarbonImmutable $newStart): Appointment
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw new SchedulingException('That appointment is not active.');
        }

        $minutes = $this->appointmentType($appointment->department, $appointment->appointment_type)['minutes'];

        return DB::transaction(function () use ($appointment, $newStart, $minutes) {
            if (! $this->isBookable($appointment->department, $appointment->appointment_type, $newStart, $appointment->id)) {
                throw new SchedulingException('That time is not available.');
            }

            $appointment->update([
                'starts_at' => $newStart,
                'ends_at' => $newStart->addMinutes($minutes),
                'reminder_sent_at' => null,
            ]);

            return $appointment;
        });
    }

    /**
     * "Tuesday, October 27 at 9:00 AM": what the agent reads aloud.
     */
    public function spoken(CarbonImmutable $time): string
    {
        return $time->setTimezone($this->timezone())->format('l, F j \a\t g:i A');
    }
}
