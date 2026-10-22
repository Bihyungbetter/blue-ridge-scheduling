<?php

namespace App\Http\Controllers\Retell;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Patient;
use App\Retell\RetellSession;
use App\Scheduling\SchedulingException;
use App\Scheduling\SchedulingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Single endpoint for every Retell custom function (POST /retell/functions).
 * Retell sends {"name": ..., "call": {...}, "args": {...}}; keep
 * "Payload: args only" (args_at_root) OFF so the call ID is present.
 *
 * Responses are read by the LLM, so they're short JSON with a `message`
 * it can paraphrase. Business-rule failures return 200 + ok:false so the
 * agent can recover; only protocol errors use 4xx.
 */
class FunctionCallController extends Controller
{
    public function __construct(private SchedulingService $scheduling) {}

    public function __invoke(Request $request): JsonResponse
    {
        $session = RetellSession::fromPayload($request->all());

        if ($session === null) {
            return response()->json(['error' => 'Missing call or chat context.'], 422);
        }

        $name = (string) $request->input('name');
        $args = (array) $request->input('args', []);

        $handler = match ($name) {
            'verify_patient' => $this->verifyPatient(...),
            'find_available_slots' => $this->findAvailableSlots(...),
            'book_appointment' => $this->bookAppointment(...),
            'list_appointments' => $this->listAppointments(...),
            'cancel_appointment' => $this->cancelAppointment(...),
            'reschedule_appointment' => $this->rescheduleAppointment(...),
            default => null,
        };

        if ($handler === null) {
            return response()->json(['error' => 'Unknown function.'], 404);
        }

        try {
            return response()->json($handler($session, $args));
        } catch (SchedulingException $e) {
            AuditLog::record($name, $session->actorType(), 'denied', [
                'conversation_id' => $session->id,
                'patient_id' => $session->verifiedPatientId(),
            ]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function verifyPatient(RetellSession $session, array $args): array
    {
        $max = config('scheduling.max_verification_attempts');

        if ($session->failedAttempts() >= $max) {
            return $this->tooManyAttempts();
        }

        $data = $this->validateArgs($args, [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date_format:Y-m-d',
        ]);

        $matches = Patient::query()
            ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim($data['last_name']))])
            ->whereDate('date_of_birth', $data['date_of_birth'])
            ->get()
            ->filter(fn (Patient $p) => mb_strtolower($p->first_name) === mb_strtolower(trim($data['first_name'])));

        if ($matches->count() !== 1) {
            $attempts = $session->recordFailedAttempt();
            AuditLog::record('verify_patient', $session->actorType(), 'denied', ['conversation_id' => $session->id]);

            if ($attempts >= $max) {
                return $this->tooManyAttempts();
            }

            return [
                'ok' => false,
                'verified' => false,
                'attempts_remaining' => $max - $attempts,
                'message' => 'Those details did not match our records. Ask the patient to confirm the spelling of their name and their date of birth.',
            ];
        }

        $patient = $matches->first();
        $session->markVerified($patient);

        Conversation::firstOrCreate(
            ['conversation_id' => $session->id],
            ['channel' => $session->channel, 'last_event' => 'verified'],
        )->update(['patient_id' => $patient->id]);

        AuditLog::record('verify_patient', $session->actorType(), 'ok', [
            'conversation_id' => $session->id,
            'patient_id' => $patient->id,
        ]);

        return ['ok' => true, 'verified' => true, 'message' => 'Identity verified.'];
    }

    private function findAvailableSlots(RetellSession $session, array $args): array
    {
        $data = $this->validateArgs($args, [
            'department' => 'required|string',
            'appointment_type' => 'required|string',
            'preferred_date' => 'nullable|date_format:Y-m-d',
        ]);

        $from = filled($data['preferred_date'] ?? null) ? $this->scheduling->parseDate($data['preferred_date']) : null;
        $slots = $this->scheduling->nextAvailableSlots($data['department'], $data['appointment_type'], $from);

        return [
            'ok' => true,
            'department' => $this->scheduling->department($data['department'])['name'],
            'appointment_type' => $this->scheduling->appointmentType($data['department'], $data['appointment_type'])['label'],
            'slots' => array_map(fn (CarbonImmutable $slot) => [
                'start_time' => $slot->format('Y-m-d\TH:i'),
                'spoken' => $this->scheduling->spoken($slot),
            ], $slots),
            'message' => $slots === []
                ? 'There are no openings in the booking window. Offer to transfer the patient to scheduling staff.'
                : 'Offer these times. Use start_time exactly as given when booking.',
        ];
    }

    private function bookAppointment(RetellSession $session, array $args): array
    {
        $patient = $this->verifiedPatient($session);
        $data = $this->validateArgs($args, [
            'department' => 'required|string',
            'appointment_type' => 'required|string',
            'start_time' => 'required|string',
        ]);

        $start = $this->scheduling->parseLocal($data['start_time']);

        // Retell may retry a timed-out request; the key makes a retry return
        // the same appointment instead of booking twice.
        $key = hash('sha256', implode('|', [$session->id, $patient->id, $data['department'], $data['appointment_type'], $start->format('Y-m-d H:i')]));

        $appointment = $this->scheduling->book($patient, $data['department'], $data['appointment_type'], $start, $session->channel, $session->id, $key);

        AuditLog::record('book_appointment', $session->actorType(), 'ok', [
            'conversation_id' => $session->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);

        return [
            'ok' => true,
            ...$this->describe($appointment),
            'message' => 'Booked. Read the date, time and department back to the patient.',
        ];
    }

    private function listAppointments(RetellSession $session, array $args): array
    {
        $patient = $this->verifiedPatient($session);

        $appointments = $patient->appointments()
            ->active()
            ->where('starts_at', '>=', $this->scheduling->now()->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        AuditLog::record('list_appointments', $session->actorType(), 'ok', [
            'conversation_id' => $session->id,
            'patient_id' => $patient->id,
        ]);

        return [
            'ok' => true,
            'appointments' => $appointments->map($this->describe(...))->all(),
            'message' => $appointments->isEmpty() ? 'The patient has no upcoming appointments.' : 'Upcoming appointments listed.',
        ];
    }

    private function cancelAppointment(RetellSession $session, array $args): array
    {
        $patient = $this->verifiedPatient($session);
        $appointment = $this->ownedAppointment($patient, $args);

        $this->scheduling->cancel($appointment);

        AuditLog::record('cancel_appointment', $session->actorType(), 'ok', [
            'conversation_id' => $session->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);

        return ['ok' => true, ...$this->describe($appointment), 'message' => 'Cancelled.'];
    }

    private function rescheduleAppointment(RetellSession $session, array $args): array
    {
        $patient = $this->verifiedPatient($session);
        $appointment = $this->ownedAppointment($patient, $args);
        $data = $this->validateArgs($args, ['new_start_time' => 'required|string']);

        $this->scheduling->reschedule($appointment, $this->scheduling->parseLocal($data['new_start_time']));

        AuditLog::record('reschedule_appointment', $session->actorType(), 'ok', [
            'conversation_id' => $session->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
        ]);

        return ['ok' => true, ...$this->describe($appointment->refresh()), 'message' => 'Rescheduled. Read the new time back to the patient.'];
    }

    private function verifiedPatient(RetellSession $session): Patient
    {
        $patient = ($id = $session->verifiedPatientId()) ? Patient::find($id) : null;

        if ($patient === null) {
            throw new SchedulingException('The patient must be verified with verify_patient first.');
        }

        return $patient;
    }

    /**
     * Load an appointment the verified patient owns and may still change.
     * Someone else's appointment gets the same "not found" as a missing one.
     */
    private function ownedAppointment(Patient $patient, array $args): Appointment
    {
        $data = $this->validateArgs($args, ['appointment_id' => 'required|integer']);
        $appointment = $patient->appointments()->find($data['appointment_id']);

        if ($appointment === null || $appointment->status !== Appointment::STATUS_SCHEDULED) {
            throw new SchedulingException('I could not find that upcoming appointment. Use list_appointments to get the right one.');
        }

        if (! $this->scheduling->patientMayChange($appointment)) {
            $hours = config('scheduling.change_cutoff_hours');

            throw new SchedulingException("Appointments within {$hours} hours can't be changed automatically. Offer to transfer the patient to scheduling staff.");
        }

        return $appointment;
    }

    private function describe(Appointment $appointment): array
    {
        return [
            'appointment_id' => $appointment->id,
            'department' => $appointment->departmentName(),
            'appointment_type' => $appointment->typeLabel(),
            'start_time' => $appointment->starts_at->format('Y-m-d\TH:i'),
            'spoken' => $this->scheduling->spoken($appointment->starts_at),
        ];
    }

    private function tooManyAttempts(): array
    {
        return [
            'ok' => false,
            'verified' => false,
            'attempts_remaining' => 0,
            'transfer_recommended' => true,
            'message' => 'Verification failed too many times. Do not discuss appointments; offer to transfer the patient to scheduling staff.',
        ];
    }

    private function validateArgs(array $args, array $rules): array
    {
        $validator = Validator::make($args, $rules);

        if ($validator->fails()) {
            $fields = implode(', ', $validator->errors()->keys());

            throw new SchedulingException("Missing or invalid: {$fields}.");
        }

        return $validator->validated();
    }
}
