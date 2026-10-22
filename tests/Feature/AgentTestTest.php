<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentTestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.retell.api_key' => 'key_test',
            'services.retell.from_number' => '+18285550199',
            'services.retell.voice_agent_id' => 'agent_voice',
            'services.retell.sms_agent_id' => 'agent_sms',
            'services.retell.sms_enabled' => false,
        ]);

        $this->actingAs(User::factory()->create());
        Http::preventStrayRequests();
    }

    public function test_page_reports_setup_status()
    {
        $this->get('/agent-test')->assertInertia(fn (Assert $page) => $page
            ->component('agent-test')
            ->where('setup.apiKey', true)
            ->where('setup.voiceAgent', true)
            ->where('setup.phoneNumber', true)
            ->where('setup.smsEnabled', false));
    }

    public function test_guests_cannot_place_calls()
    {
        auth()->logout();

        $this->post('/agent-test/call', ['phone' => '8285550123'])->assertRedirect('/login');
    }

    public function test_call_normalises_the_number_and_sends_context()
    {
        Http::fake(['api.retellai.com/v2/create-phone-call' => Http::response(['call_id' => 'call_123'], 201)]);

        $this->from('/agent-test')->post('/agent-test/call', ['phone' => '(828) 555-0123'])
            ->assertRedirect('/agent-test')
            ->assertSessionHas('status');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.retellai.com/v2/create-phone-call'
            && $r->hasHeader('Authorization', 'Bearer key_test')
            && $r['to_number'] === '+18285550123'
            && $r['from_number'] === '+18285550199'
            && $r['override_agent_id'] === 'agent_voice'
            && is_string($r['retell_llm_dynamic_variables']['departments'])
            && $r['metadata']['purpose'] === 'staff_test');

        $log = AuditLog::where('action', 'test_call')->sole();
        $this->assertSame('call_123', $log->conversation_id);
        $this->assertStringNotContainsString('5550123', json_encode($log->toArray()));
    }

    public function test_invalid_numbers_are_rejected_before_calling_retell()
    {
        foreach (['123', '555-0123', '(028) 555-0123', '+44 20 7946 0000'] as $bad) {
            $this->from('/agent-test')->post('/agent-test/call', ['phone' => $bad])->assertSessionHasErrors('phone');
        }

        Http::assertNothingSent();
    }

    public function test_retell_errors_are_shown_not_thrown()
    {
        Http::fake(['api.retellai.com/*' => Http::response(['message' => 'KYC verification required'], 403)]);

        $this->from('/agent-test')->post('/agent-test/call', ['phone' => '8285550123'])
            ->assertSessionHasErrors(['call' => 'Retell error (403): KYC verification required']);

        $this->assertSame('error', AuditLog::where('action', 'test_call')->sole()->outcome);
    }

    public function test_texting_is_blocked_until_enabled()
    {
        $this->from('/agent-test')->post('/agent-test/text', ['text_phone' => '8285550123'])->assertSessionHasErrors('text');
        Http::assertNothingSent();

        config(['services.retell.sms_enabled' => true]);
        Http::fake(['api.retellai.com/create-sms-chat' => Http::response(['chat_id' => 'chat_1'])]);

        $this->from('/agent-test')->post('/agent-test/text', ['text_phone' => '828-555-0123'])->assertSessionHas('status');
        Http::assertSent(fn (Request $r) => $r['to_number'] === '+18285550123' && $r['override_agent_id'] === 'agent_sms');
    }

    public function test_web_call_returns_only_the_client_token_fields()
    {
        config(['services.retell.from_number' => null]); // browser calls need no number
        Http::fake(['api.retellai.com/v3/create-web-call' => Http::response([
            'call_id' => 'call_web', 'access_token' => 'tok', 'transport' => 'livekit', 'agent_id' => 'agent_voice', 'call_status' => 'registered',
        ], 201)]);

        $this->postJson('/agent-test/web-call')
            ->assertOk()
            ->assertExactJson(['call_id' => 'call_web', 'access_token' => 'tok', 'transport' => 'livekit']);

        Http::assertSent(fn (Request $r) => $r['agent_id'] === 'agent_voice');
    }

    public function test_web_call_reports_missing_configuration()
    {
        config(['services.retell.api_key' => null]);

        $this->postJson('/agent-test/web-call')->assertStatus(422)->assertJson(['message' => 'Set RETELL_API_KEY first.']);
    }
}
