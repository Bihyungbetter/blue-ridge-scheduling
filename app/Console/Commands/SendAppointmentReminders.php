<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Retell\RetellClient;
use App\Scheduling\SchedulingService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Starter reminder job: one SMS chat per opted-in patient for tomorrow's
 * appointments. Only the time and department go to Retell (minimum
 * necessary); the chat agent's prompt turns them into the message.
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders {--dry-run : List who would be texted without sending}';

    protected $description = "Text opted-in patients a reminder for tomorrow's appointments via Retell";

    public function handle(SchedulingService $scheduling, RetellClient $retell): int
    {
        $tomorrow = $scheduling->now()->addDay()->startOfDay();

        $appointments = Appointment::active()
            ->with('patient')
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [$tomorrow->format('Y-m-d H:i:s'), $tomorrow->endOfDay()->format('Y-m-d H:i:s')])
            ->whereHas('patient', fn ($q) => $q->where('sms_opt_in', true)->whereNotNull('phone'))
            ->get();

        if (! $this->option('dry-run') && ! $retell->isConfigured()) {
            $this->components->error('Set RETELL_API_KEY and RETELL_FROM_NUMBER first.');

            return self::FAILURE;
        }

        foreach ($appointments as $appointment) {
            if ($this->option('dry-run')) {
                $this->line("Would remind appointment #{$appointment->id}");

                continue;
            }

            try {
                $retell->createSmsChat($appointment->patient->phone, [
                    'appointment_time' => $scheduling->spoken($appointment->starts_at),
                    'department_name' => $appointment->departmentName(),
                    'appointment_id' => (string) $appointment->id,
                ], ['appointment_id' => $appointment->id, 'purpose' => 'reminder']);

                $appointment->update(['reminder_sent_at' => now()]);
                $outcome = 'ok';
            } catch (Throwable $e) {
                // Log the failure class only; HTTP errors can echo the phone number.
                report(new \RuntimeException("Reminder failed for appointment #{$appointment->id}: ".$e::class));
                $outcome = 'error';
            }

            AuditLog::record('send_reminder', 'system', $outcome, [
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
            ]);
        }

        $this->components->info("{$appointments->count()} reminder(s) processed.");

        return self::SUCCESS;
    }
}
