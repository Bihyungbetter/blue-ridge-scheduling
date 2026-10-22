<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * DEMO DATA ONLY: fake patients and appointments for local development.
 * Never run against a database holding real patients.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Staff',
            'email' => 'test@example.com',
        ]);

        $patients = Patient::factory(40)->create();
        $today = CarbonImmutable::now(config('scheduling.timezone'))->startOfDay();

        foreach (range(0, 6) as $offset) {
            $day = $today->addDays($offset);

            foreach (config('scheduling.departments') as $key => $dept) {
                if (! isset($dept['hours'][$day->dayOfWeekIso])) {
                    continue;
                }

                [$open, $close] = $dept['hours'][$day->dayOfWeekIso];
                $typeKey = array_key_first($dept['appointment_types']);
                $minutes = $dept['appointment_types'][$typeKey]['minutes'];

                // Non-overlapping starts, one appointment per block.
                $times = [];
                for ($t = $day->setTimeFromTimeString($open); $t->addMinutes($minutes)->lessThanOrEqualTo($day->setTimeFromTimeString($close)); $t = $t->addMinutes($minutes)) {
                    $times[] = $t;
                }

                foreach (fake()->randomElements($times, min(4, count($times))) as $start) {
                    Appointment::factory()->at($start, $minutes)->create([
                        'patient_id' => $patients->random()->id,
                        'department' => $key,
                        'appointment_type' => $typeKey,
                        'source' => fake()->randomElement(['staff', 'voice', 'voice', 'sms']),
                    ]);
                }
            }
        }

        foreach (range(1, 8) as $i) {
            $started = now()->subHours($i * 3);
            $transferred = $i % 4 === 0;

            Conversation::create([
                'conversation_id' => 'demo_'.$i,
                'channel' => $i % 3 === 0 ? 'sms' : 'voice',
                'direction' => 'inbound',
                'last_event' => 'call_analyzed',
                'patient_id' => $patients->random()->id,
                'disconnection_reason' => $transferred ? 'call_transfer' : 'agent_hangup',
                'summary' => $transferred
                    ? 'Caller asked to speak with a person about a referral; transferred to scheduling staff.'
                    : 'Caller verified identity and booked a follow-up visit.',
                'user_sentiment' => $transferred ? 'Neutral' : 'Positive',
                'successful' => ! $transferred,
                'transferred' => $transferred,
                'duration_ms' => fake()->numberBetween(45, 240) * 1000,
                'started_at' => $started,
                'ended_at' => $started->copy()->addMinutes(3),
            ]);
        }
    }
}
