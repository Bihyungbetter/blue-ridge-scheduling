<?php

namespace Tests\Feature\Retell;

use App\Retell\RetellSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SendsRetellRequests;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    use RefreshDatabase, SendsRetellRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRetell();
    }

    public function test_signature_round_trips()
    {
        $signature = RetellSignature::sign('{"a":1}', 'key', 1_700_000_000_000);

        $this->assertMatchesRegularExpression('/^v=1700000000000,d=[0-9a-f]{64}$/', $signature);
        $this->assertTrue(RetellSignature::verify('{"a":1}', 'key', $signature, 1_700_000_000_000));
    }

    public function test_tampered_body_wrong_key_and_stale_timestamps_fail()
    {
        $signature = RetellSignature::sign('{"a":1}', 'key', 1_700_000_000_000);

        $this->assertFalse(RetellSignature::verify('{"a":2}', 'key', $signature, 1_700_000_000_000));
        $this->assertFalse(RetellSignature::verify('{"a":1}', 'other', $signature, 1_700_000_000_000));
        $this->assertFalse(RetellSignature::verify('{"a":1}', 'key', $signature, 1_700_000_000_000 + 300_001));
        $this->assertFalse(RetellSignature::verify('{"a":1}', 'key', 'garbage', 1_700_000_000_000));
    }

    public function test_unsigned_and_badly_signed_requests_are_rejected()
    {
        $payload = ['event' => 'call_started', 'call' => ['call_id' => 'c1']];

        $this->retellPost('/retell/webhook', $payload, '')->assertUnauthorized();
        $this->retellPost('/retell/webhook', $payload, RetellSignature::sign(json_encode($payload), 'wrong-key'))->assertUnauthorized();
        $this->retellPost('/retell/webhook', $payload)->assertNoContent();
    }

    public function test_requests_are_refused_when_the_api_key_is_missing()
    {
        $payload = ['event' => 'call_started', 'call' => ['call_id' => 'c1']];
        $signed = RetellSignature::sign(json_encode($payload), $this->retellKey);
        config(['services.retell.api_key' => null]);

        $this->retellPost('/retell/webhook', $payload, $signed)->assertStatus(503);
    }

    public function test_retell_routes_skip_csrf_and_sessions()
    {
        $this->retellPost('/retell/inbound', ['event' => 'call_inbound', 'call_inbound' => ['from_number' => '+18285550100']])
            ->assertOk()
            ->assertCookieMissing(config('session.cookie'));
    }
}
