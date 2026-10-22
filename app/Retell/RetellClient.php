<?php

namespace App\Retell;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Retell REST API (https://docs.retellai.com/api-references). Paths reflect
 * the October 2026 API, including the v2/v3 moves.
 */
class RetellClient
{
    /**
     * Start an SMS conversation. The chat agent writes the text itself,
     * using the dynamic variables; there is no raw message body.
     *
     * Requires an SMS-enabled Retell (Twilio) number with an approved A2P
     * 10DLC campaign; until then Retell rejects the request.
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
     * Outbound call from RETELL_FROM_NUMBER. The number needs an outbound
     * agent bound (retell:setup does that) and the workspace must have
     * passed Retell's KYC check.
     *
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

    /**
     * Browser call: returns call_id + access_token for retell-client-js-sdk.
     * Needs no phone number and no KYC.
     *
     * @param  array<string, string>  $dynamicVariables
     */
    public function createWebCall(array $dynamicVariables, array $metadata = []): array
    {
        return $this->http(requireNumber: false)->post('/v3/create-web-call', array_filter([
            'agent_id' => config('services.retell.voice_agent_id'),
            'retell_llm_dynamic_variables' => $dynamicVariables,
            'metadata' => $metadata ?: null,
        ]))->json();
    }

    // --- Agent provisioning (used by `php artisan retell:setup`) ---

    public function createRetellLlm(array $body): array
    {
        return $this->http(requireNumber: false)->post('/create-retell-llm', $body)->json();
    }

    public function updateRetellLlm(string $llmId, array $body): array
    {
        return $this->http(requireNumber: false)->patch('/update-retell-llm/'.urlencode($llmId), $body)->json();
    }

    public function createAgent(array $body): array
    {
        return $this->http(requireNumber: false)->post('/create-agent', $body)->json();
    }

    public function updateAgent(string $agentId, array $body): array
    {
        return $this->http(requireNumber: false)->patch('/update-agent/'.urlencode($agentId), $body)->json();
    }

    public function createChatAgent(array $body): array
    {
        return $this->http(requireNumber: false)->post('/create-chat-agent', $body)->json();
    }

    public function updateChatAgent(string $agentId, array $body): array
    {
        return $this->http(requireNumber: false)->patch('/update-chat-agent/'.urlencode($agentId), $body)->json();
    }

    public function updatePhoneNumber(string $number, array $body): array
    {
        return $this->http(requireNumber: false)->patch('/update-phone-number/'.urlencode($number), $body)->json();
    }

    public function listVoices(): array
    {
        return $this->http(requireNumber: false)->get('/list-voices')->json();
    }

    public function hasApiKey(): bool
    {
        return filled(config('services.retell.api_key'));
    }

    public function isConfigured(): bool
    {
        return $this->hasApiKey() && filled(config('services.retell.from_number'));
    }

    private function http(bool $requireNumber = true): PendingRequest
    {
        if (! $this->hasApiKey() || ($requireNumber && ! $this->isConfigured())) {
            throw new RuntimeException($requireNumber
                ? 'Set RETELL_API_KEY and RETELL_FROM_NUMBER to send outbound messages.'
                : 'Set RETELL_API_KEY first.');
        }

        return Http::baseUrl(config('services.retell.base_url'))
            ->withToken(config('services.retell.api_key'))
            ->acceptJson()
            ->timeout(20)
            ->throw();
    }

    private function fromNumber(): string
    {
        return config('services.retell.from_number');
    }
}
