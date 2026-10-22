<?php

namespace Tests\Feature\Retell;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SendsRetellRequests;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase, SendsRetellRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRetell();
    }

    private function call_payload(string $event, array $extra = []): array
    {
        return ['event' => $event, 'call' => [
            'call_id' => 'call_abc',
            'direction' => 'inbound',
            'from_number' => '+18285550123',
            'start_timestamp' => 1_793_000_000_000,
            'transcript' => 'Agent: Hi. User: My name is Jordan Rivera, born April 12 1980.',
            ...$extra,
        ]];
    }

    public function test_events_upsert_one_conversation_and_drop_the_transcript()
    {
        $this->retellPost('/retell/webhook', $this->call_payload('call_started'))->assertNoContent();
        $this->retellPost('/retell/webhook', $this->call_payload('call_ended', [
            'end_timestamp' => 1_793_000_090_000,
            'duration_ms' => 90_000,
            'disconnection_reason' => 'agent_hangup',
        ]))->assertNoContent();
        $this->retellPost('/retell/webhook', $this->call_payload('call_analyzed', [
            'call_analysis' => ['call_summary' => 'Booked a follow-up.', 'user_sentiment' => 'Positive', 'call_successful' => true],
        ]))->assertNoContent();
        // Retell retries: a duplicate must be harmless.
        $this->retellPost('/retell/webhook', $this->call_payload('call_analyzed'))->assertNoContent();

        $conversation = Conversation::sole();
        $this->assertSame('call_abc', $conversation->conversation_id);
        $this->assertSame('voice', $conversation->channel);
        $this->assertSame('call_analyzed', $conversation->last_event);
        $this->assertSame('agent_hangup', $conversation->disconnection_reason);
        $this->assertSame(90_000, $conversation->duration_ms);
        $this->assertSame('Booked a follow-up.', $conversation->summary);
        $this->assertTrue($conversation->successful);

        $stored = json_encode(Conversation::all()->toArray());
        $this->assertStringNotContainsString('Rivera', $stored);
        $this->assertStringNotContainsString('5550123', $stored);
    }

    public function test_transfers_are_flagged()
    {
        $this->retellPost('/retell/webhook', $this->call_payload('call_ended', ['disconnection_reason' => 'call_transfer']));

        $this->assertTrue(Conversation::sole()->transferred);
    }

    public function test_chat_events_are_recorded_as_sms()
    {
        $this->retellPost('/retell/webhook', ['event' => 'chat_ended', 'chat' => ['chat_id' => 'chat_1']])->assertNoContent();

        $this->assertSame('sms', Conversation::sole()->channel);
    }

    public function test_inbound_call_gets_string_dynamic_variables()
    {
        config(['scheduling.transfer_number' => '+18285550100']);

        $vars = $this->retellPost('/retell/inbound', [
            'event' => 'call_inbound',
            'call_inbound' => ['from_number' => '+18285550123', 'to_number' => '+18285550199'],
        ])->assertOk()->json('call_inbound.dynamic_variables');

        $this->assertSame('yes', $vars['transfer_available']);
        $this->assertStringContainsString('primary_care = Primary Care', $vars['departments']);
        foreach ($vars as $value) {
            $this->assertIsString($value);
        }
    }

    public function test_inbound_sms_uses_the_chat_key()
    {
        $this->retellPost('/retell/inbound', ['event' => 'chat_inbound', 'chat_inbound' => ['chat_id' => 'chat_1']])
            ->assertOk()
            ->assertJsonStructure(['chat_inbound' => ['dynamic_variables']]);
    }
}
