<?php

namespace Tests\Concerns;

use App\Retell\RetellSignature;
use Illuminate\Testing\TestResponse;

trait SendsRetellRequests
{
    protected string $retellKey = 'test-retell-key';

    protected function setUpRetell(): void
    {
        config(['services.retell.api_key' => $this->retellKey]);
    }

    /**
     * POST a raw JSON body signed exactly as Retell signs it. (postJson would
     * re-encode the array, so the signed bytes could differ from the sent ones.)
     */
    protected function retellPost(string $uri, array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RETELL_SIGNATURE' => $signature ?? RetellSignature::sign($body, $this->retellKey),
        ], $body);
    }

    protected function callFunction(string $name, array $args = [], string $callId = 'call_test_1'): TestResponse
    {
        return $this->retellPost('/retell/functions', [
            'name' => $name,
            'call' => ['call_id' => $callId, 'call_type' => 'phone_call', 'direction' => 'inbound'],
            'args' => $args,
        ]);
    }
}
