<?php

namespace Tests\Feature\Retell;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.retell.api_key' => 'key_test',
            'services.retell.from_number' => '+18285550199',
            'services.retell.llm_id' => null,
            'services.retell.sms_llm_id' => null,
            'services.retell.voice_agent_id' => null,
            'services.retell.sms_agent_id' => null,
            'services.retell.sms_enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    private function fakeRetell(): void
    {
        Http::fake([
            'api.retellai.com/create-retell-llm' => Http::sequence()->push(['llm_id' => 'llm_voice'], 201)->push(['llm_id' => 'llm_sms'], 201),
            'api.retellai.com/create-agent' => Http::response(['agent_id' => 'agent_voice'], 201),
            'api.retellai.com/create-chat-agent' => Http::response(['agent_id' => 'agent_sms'], 201),
            'api.retellai.com/update-phone-number/*' => Http::response(['phone_number' => '+18285550199']),
        ]);
    }

    public function test_creates_agents_and_binds_the_number()
    {
        $this->fakeRetell();

        $this->artisan('retell:setup', ['--url' => 'https://demo.example.org'])
            ->expectsOutputToContain('RETELL_VOICE_AGENT_ID=agent_voice')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/create-retell-llm')
            && $r['start_speaker'] === 'agent'
            && collect($r['general_tools'])->contains('name', 'end_call')
            && ! str_contains($r['begin_message'] ?? 'calling', 'calling') // also used when the agent places the call
            && collect($r['general_tools'])->firstWhere('name', 'book_appointment')['url'] === 'https://demo.example.org/retell/functions');

        // The SMS LLM gets no call-only tools.
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/create-retell-llm')
            && ! collect($r['general_tools'])->contains('name', 'end_call')
            && collect($r['general_tools'])->contains('name', 'verify_patient'));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/create-agent')
            && $r['response_engine'] === ['type' => 'retell-llm', 'llm_id' => 'llm_voice']
            && $r['webhook_url'] === 'https://demo.example.org/retell/webhook'
            && $r['data_storage_setting'] === 'everything_except_pii'
            && in_array('date_of_birth', $r['pii_config']['categories'], true));

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && str_ends_with(urldecode($r->url()), '/update-phone-number/+18285550199')
            && $r['outbound_agents'] === [['agent_id' => 'agent_voice', 'weight' => 1]]
            && $r['inbound_webhook_url'] === 'https://demo.example.org/retell/inbound'
            && ! isset($r['inbound_sms_agents']));
    }

    public function test_re_runs_update_instead_of_duplicating()
    {
        config(['services.retell.llm_id' => 'llm_voice', 'services.retell.voice_agent_id' => 'agent_voice',
            'services.retell.sms_llm_id' => 'llm_sms', 'services.retell.sms_agent_id' => 'agent_sms']);

        Http::fake([
            'api.retellai.com/update-retell-llm/*' => Http::sequence()->push(['llm_id' => 'llm_voice'])->push(['llm_id' => 'llm_sms']),
            'api.retellai.com/update-agent/*' => Http::response(['agent_id' => 'agent_voice']),
            'api.retellai.com/update-chat-agent/*' => Http::response(['agent_id' => 'agent_sms']),
            'api.retellai.com/update-phone-number/*' => Http::response([]),
        ]);

        $this->artisan('retell:setup', ['--url' => 'https://demo.example.org'])->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/create-'));
    }

    public function test_requires_https_and_an_api_key()
    {
        $this->artisan('retell:setup', ['--url' => 'http://localhost:8000'])->assertFailed();

        config(['services.retell.api_key' => null]);
        $this->artisan('retell:setup', ['--url' => 'https://demo.example.org'])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_api_errors_are_reported()
    {
        Http::fake(['api.retellai.com/*' => Http::response(['message' => 'Invalid voice_id'], 400)]);

        $this->artisan('retell:setup', ['--url' => 'https://demo.example.org'])
            ->expectsOutputToContain('Invalid voice_id')
            ->assertFailed();
    }
}
