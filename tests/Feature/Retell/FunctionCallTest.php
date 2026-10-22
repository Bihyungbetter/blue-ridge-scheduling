<?php

namespace Tests\Feature\Retell;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SendsRetellRequests;
use Tests\TestCase;

class FunctionCallTest extends TestCase
{
    use RefreshDatabase, SendsRetellRequests;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRetell();

        // Monday 8:00 AM. Primary care opens 8:00, lead time is 2 hours.
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'America/New_York'));

        $this->patient = Patient::factory()->create([
            'first_name' => 'Jordan',
            'last_name' => 'Rivera',
            'date_of_birth' => '1980-04-12',
        ]);
    }

    private function verify(string $callId = 'call_test_1'): void
    {
        $this->callFunction('verify_patient', [
            'first_name' => 'jordan',
            'last_name' => 'RIVERA',
            'date_of_birth' => '1980-04-12',
        ], $callId)->assertJson(['ok' => true, 'verified' => true]);
    }

    private function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'America/New_York');
    }

    public function test_slots_respect_lead_time_hours_and_limit()
    {
        $response = $this->callFunction('find_available_slots', ['department' => 'primary_care', 'appointment_type' => 'follow_up'])
            ->assertOk()
            ->assertJson(['ok' => true, 'department' => 'Primary Care']);

        $slots = $response->json('slots');
        $this->assertCount(5, $slots);
        $this->assertSame('2026-10-26T10:00', $slots[0]['start_time']);
        $this->assertSame('Monday, October 26 at 10:00 AM', $slots[0]['spoken']);
    }

    public function test_full_slots_are_not_offered_or_bookable()
    {
        // Primary care capacity is 2.
        Appointment::factory()->count(2)->at($this->at('2026-10-26 10:00'))->create();

        $slots = $this->callFunction('find_available_slots', ['department' => 'primary_care', 'appointment_type' => 'follow_up'])->json('slots');
        $this->assertSame('2026-10-26T10:20', $slots[0]['start_time']);

        $this->verify();
        $this->callFunction('book_appointment', ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => '2026-10-26T10:00'])
            ->assertJson(['ok' => false, 'message' => 'That time is no longer available.']);
    }

    public function test_closed_days_and_holidays_are_skipped()
    {
        // Saturday: primary care is closed, so the first slot is Monday 8:00.
        $slots = $this->callFunction('find_available_slots', [
            'department' => 'primary_care', 'appointment_type' => 'follow_up', 'preferred_date' => '2026-10-31',
        ])->json('slots');
        $this->assertSame('2026-11-02T08:00', $slots[0]['start_time']);

        // Thanksgiving is a configured holiday.
        $slots = $this->callFunction('find_available_slots', [
            'department' => 'primary_care', 'appointment_type' => 'follow_up', 'preferred_date' => '2026-11-26',
        ])->json('slots');
        $this->assertSame('2026-11-27T08:00', $slots[0]['start_time']);
    }

    public function test_off_grid_or_out_of_hours_times_are_refused()
    {
        $this->verify();

        foreach (['2026-10-26T10:05', '2026-10-26T17:00', '2026-10-26T09:00', 'tomorrow at ten'] as $time) {
            $this->callFunction('book_appointment', ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => $time])
                ->assertJson(['ok' => false]);
        }

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_booking_requires_verification()
    {
        $this->callFunction('book_appointment', ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => '2026-10-26T10:00'])
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => 'The patient must be verified with verify_patient first.']);

        $this->callFunction('list_appointments')->assertJson(['ok' => false]);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_verification_is_per_conversation()
    {
        $this->verify('call_a');

        $this->callFunction('list_appointments', [], 'call_b')->assertJson(['ok' => false]);
        $this->callFunction('list_appointments', [], 'call_a')->assertJson(['ok' => true]);
    }

    public function test_verified_patient_can_book_and_retries_do_not_double_book()
    {
        $this->verify();

        $args = ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => '2026-10-26T10:00'];

        $first = $this->callFunction('book_appointment', $args)
            ->assertJson(['ok' => true, 'department' => 'Primary Care', 'spoken' => 'Monday, October 26 at 10:00 AM']);
        $retry = $this->callFunction('book_appointment', $args)->assertJson(['ok' => true]);

        $this->assertSame($first->json('appointment_id'), $retry->json('appointment_id'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointments', [
            'patient_id' => $this->patient->id,
            'source' => 'voice',
            'conversation_id' => 'call_test_1',
            'starts_at' => '2026-10-26 10:00:00',
            'ends_at' => '2026-10-26 10:20:00',
        ]);
    }

    public function test_rebooking_a_cancelled_slot_in_the_same_call_creates_a_new_appointment()
    {
        $this->verify();
        $args = ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => '2026-10-29T09:00'];

        $first = $this->callFunction('book_appointment', $args)->json('appointment_id');
        $this->callFunction('cancel_appointment', ['appointment_id' => $first])->assertJson(['ok' => true]);
        $second = $this->callFunction('book_appointment', $args)->assertJson(['ok' => true])->json('appointment_id');

        $this->assertNotSame($first, $second);
        $this->assertSame(Appointment::STATUS_SCHEDULED, Appointment::find($second)->status);
    }

    public function test_rebooking_the_original_time_after_reschedule_creates_a_new_appointment()
    {
        $this->verify();
        $args = ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => '2026-10-29T09:00'];

        $first = $this->callFunction('book_appointment', $args)->json('appointment_id');
        $this->callFunction('reschedule_appointment', ['appointment_id' => $first, 'new_start_time' => '2026-10-29T11:00'])->assertJson(['ok' => true]);
        $this->callFunction('book_appointment', $args)->assertJson(['ok' => true, 'start_time' => '2026-10-29T09:00']);

        $this->assertSame(2, Appointment::active()->count());
    }

    public function test_failed_verification_locks_out_after_max_attempts()
    {
        foreach ([2, 1] as $remaining) {
            $this->callFunction('verify_patient', ['first_name' => 'Jordan', 'last_name' => 'Rivera', 'date_of_birth' => '1980-04-13'])
                ->assertJson(['ok' => false, 'attempts_remaining' => $remaining]);
        }

        $this->callFunction('verify_patient', ['first_name' => 'Jordan', 'last_name' => 'Rivera', 'date_of_birth' => '1980-04-13'])
            ->assertJson(['ok' => false, 'attempts_remaining' => 0, 'transfer_recommended' => true]);

        // Even correct details are refused once locked out.
        $this->callFunction('verify_patient', ['first_name' => 'Jordan', 'last_name' => 'Rivera', 'date_of_birth' => '1980-04-12'])
            ->assertJson(['ok' => false, 'transfer_recommended' => true]);
    }

    public function test_patient_cannot_touch_someone_elses_appointment()
    {
        $other = Appointment::factory()->at($this->at('2026-10-29 09:00'))->create();
        $this->verify();

        $this->callFunction('cancel_appointment', ['appointment_id' => $other->id])
            ->assertJson(['ok' => false]);

        $this->assertSame(Appointment::STATUS_SCHEDULED, $other->fresh()->status);
    }

    public function test_cancel_respects_the_change_cutoff()
    {
        $soon = Appointment::factory()->for($this->patient)->at($this->at('2026-10-26 15:00'))->create();
        $later = Appointment::factory()->for($this->patient)->at($this->at('2026-10-29 09:00'))->create();
        $this->verify();

        $this->callFunction('cancel_appointment', ['appointment_id' => $soon->id])->assertJson(['ok' => false]);
        $this->callFunction('cancel_appointment', ['appointment_id' => $later->id])->assertJson(['ok' => true]);

        $this->assertSame(Appointment::STATUS_SCHEDULED, $soon->fresh()->status);
        $this->assertSame(Appointment::STATUS_CANCELLED, $later->fresh()->status);
    }

    public function test_reschedule_moves_the_appointment()
    {
        $appointment = Appointment::factory()->for($this->patient)->at($this->at('2026-10-29 09:00'))->create();
        $this->verify();

        $this->callFunction('reschedule_appointment', ['appointment_id' => $appointment->id, 'new_start_time' => '2026-10-29T11:20'])
            ->assertJson(['ok' => true, 'start_time' => '2026-10-29T11:20']);

        $this->assertSame('2026-10-29 11:40:00', $appointment->fresh()->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_list_returns_only_upcoming_active_appointments()
    {
        Appointment::factory()->for($this->patient)->at($this->at('2026-10-29 09:00'))->create();
        Appointment::factory()->for($this->patient)->at($this->at('2026-10-30 09:00'))->create(['status' => 'cancelled']);
        Appointment::factory()->for($this->patient)->at($this->at('2026-10-20 09:00'))->create();
        $this->verify();

        $this->callFunction('list_appointments')->assertJsonCount(1, 'appointments');
    }

    public function test_audit_log_records_ids_only()
    {
        $this->verify();
        $this->callFunction('book_appointment', ['department' => 'primary_care', 'appointment_type' => 'follow_up', 'start_time' => '2026-10-26T10:00']);

        $log = AuditLog::where('action', 'book_appointment')->sole();
        $this->assertSame('ai_voice', $log->actor_type);
        $this->assertSame($this->patient->id, $log->patient_id);
        $this->assertStringNotContainsString('Rivera', json_encode(AuditLog::all()->toArray()));
    }

    public function test_database_errors_do_not_log_patient_details()
    {
        Log::spy();

        // Force a QueryException on the patient lookup, whose bindings carry PHI.
        Schema::withoutForeignKeyConstraints(fn () => Schema::drop('patients'));
        $this->callFunction('verify_patient', ['first_name' => 'Jordan', 'last_name' => 'Rivera', 'date_of_birth' => '1980-04-12'])
            ->assertServerError();

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Database query failed'
            && ! str_contains(strtolower(json_encode($context)), 'rivera')
            && ! str_contains(json_encode($context), '1980'));
    }

    public function test_unknown_function_and_missing_context_are_protocol_errors()
    {
        $this->callFunction('drop_tables')->assertNotFound();
        $this->retellPost('/retell/functions', ['name' => 'list_appointments', 'args' => []])->assertUnprocessable();
    }

    public function test_invalid_arguments_get_a_readable_message()
    {
        $this->callFunction('find_available_slots', ['department' => 'dermatology', 'appointment_type' => 'follow_up'])
            ->assertJson(['ok' => false, 'message' => 'That department is not available for scheduling.']);

        $this->callFunction('find_available_slots', ['department' => 'primary_care'])
            ->assertJson(['ok' => false, 'message' => 'Missing or invalid: appointment_type.']);
    }
}
