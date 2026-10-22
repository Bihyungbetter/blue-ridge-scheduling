<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@example.com'])->assertNotFound();
    }

    public function test_guests_cannot_see_patient_data()
    {
        $this->get('/appointments')->assertRedirect('/login');
        $this->get('/conversations')->assertRedirect('/login');
    }

    public function test_appointments_page_filters_by_day()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00', 'America/New_York'));
        Appointment::factory()->at(CarbonImmutable::parse('2026-10-26 09:00', 'America/New_York'))->create();
        Appointment::factory()->at(CarbonImmutable::parse('2026-10-27 09:00', 'America/New_York'))->create();

        $this->actingAs(User::factory()->create())
            ->get('/appointments')
            ->assertInertia(fn (Assert $page) => $page
                ->component('appointments/index')
                ->has('appointments', 1)
                ->where('appointments.0.time', '9:00 AM')
                ->where('filters.date', '2026-10-26'));

        $this->get('/appointments?date=2026-10-27')
            ->assertInertia(fn (Assert $page) => $page->has('appointments', 1)->where('filters.date', '2026-10-27'));
    }

    public function test_staff_can_cancel_and_it_is_audited()
    {
        $user = User::factory()->create();
        $appointment = Appointment::factory()->create();

        $this->actingAs($user)->post("/appointments/{$appointment->id}/cancel")->assertRedirect();

        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'cancel_appointment', 'actor_type' => 'staff', 'user_id' => $user->id]);
    }

    public function test_dashboard_and_conversations_render()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->component('dashboard')->has('stats')->has('schedule'));
        $this->get('/conversations')->assertInertia(fn (Assert $page) => $page->component('conversations/index'));
    }

    public function test_viewing_the_schedule_is_audited()
    {
        $this->actingAs($user = User::factory()->create())->get('/appointments');

        $this->assertSame($user->id, AuditLog::where('action', 'view_schedule')->sole()->user_id);
    }
}
