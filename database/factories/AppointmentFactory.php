<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::now(config('scheduling.timezone'))->addDays(3)->setTime(9, 0);

        return [
            'patient_id' => Patient::factory(),
            'department' => 'primary_care',
            'appointment_type' => 'follow_up',
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(20),
            'status' => Appointment::STATUS_SCHEDULED,
            'source' => 'staff',
        ];
    }

    public function at(CarbonImmutable $start, int $minutes = 20): static
    {
        return $this->state(['starts_at' => $start, 'ends_at' => $start->addMinutes($minutes)]);
    }
}
