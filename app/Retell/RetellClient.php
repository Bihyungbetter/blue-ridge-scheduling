<?php

namespace App\Retell;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbound Retell API calls (reminder texts and calls).
 */
class RetellClient
{
    /**
     * Start an SMS conversation. The chat agent writes the text itself,
     * using the dynamic variables; there is no raw message body.
     *
     * @param  array<string, string>  $dynamicVariables
     */
    public function createSmsChat(string $toNumber, array $dynamicVariables, array $metadata = []): array
    {
        return $this->http()->post('/create-sms-chat', array_filter([
            'from_number' => $this->fromNumber(),
            'to_number' => $toNumber,
            'override_agent_id' => config('services.retell.sms_agent_id'),
            'retell_llm_dynamic_variables' => $dynamicVariables,
            'metadata' => $metadata ?: null,
        ]))->json();
    }

    /**
     * @param  array<string, string>  $dynamicVariables
     */
    public function createPhoneCall(string $toNumber, array $dynamicVariables, array $metadata = []): array
    {
        return $this->http()->post('/v2/create-phone-call', array_filter([
            'from_number' => $this->fromNumber(),
            'to_number' => $toNumber,
            'override_agent_id' => config('services.retell.voice_agent_id'),
            'retell_llm_dynamic_variables' => $dynamicVariables,
            'metadata' => $metadata ?: null,
        ]))->json();
    }

    public function isConfigured(): bool
    {
        return filled(config('services.retell.api_key')) && filled(config('services.retell.from_number'));
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Set RETELL_API_KEY and RETELL_FROM_NUMBER to send outbound messages.');
        }

        return Http::baseUrl(config('services.retell.base_url'))
            ->withToken(config('services.retell.api_key'))
            ->acceptJson()
            ->timeout(15)
            ->throw();
    }

    private function fromNumber(): string
    {
        return config('services.retell.from_number');
    }
}
