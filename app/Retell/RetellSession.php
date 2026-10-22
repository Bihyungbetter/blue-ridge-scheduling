<?php

namespace App\Retell;

use App\Models\Patient;
use Illuminate\Support\Facades\Cache;

/**
 * Per-conversation state for one Retell call or SMS chat. Identity
 * verification lives server-side, keyed by Retell's call/chat ID, so the
 * LLM can't claim a caller is verified when they aren't.
 */
final class RetellSession
{
    private const TTL_HOURS = 2;

    private function __construct(
        public readonly string $id,
        public readonly string $channel,
    ) {}

    public static function fromPayload(array $payload): ?self
    {
        $call = $payload['call'] ?? null;
        $chat = $payload['chat'] ?? null;

        return match (true) {
            is_array($call) && filled($call['call_id'] ?? null) => new self($call['call_id'], 'voice'),
            // How chat agents wrap function calls isn't documented; accept either shape.
            is_array($call) && filled($call['chat_id'] ?? null) => new self($call['chat_id'], 'sms'),
            is_array($chat) && filled($chat['chat_id'] ?? null) => new self($chat['chat_id'], 'sms'),
            default => null,
        };
    }

    public function actorType(): string
    {
        return $this->channel === 'sms' ? 'ai_sms' : 'ai_voice';
    }

    public function verifiedPatientId(): ?int
    {
        return Cache::get($this->key('patient'));
    }

    public function markVerified(Patient $patient): void
    {
        Cache::put($this->key('patient'), $patient->id, now()->addHours(self::TTL_HOURS));
    }

    public function failedAttempts(): int
    {
        return (int) Cache::get($this->key('failed'), 0);
    }

    public function recordFailedAttempt(): int
    {
        Cache::add($this->key('failed'), 0, now()->addHours(self::TTL_HOURS));

        return (int) Cache::increment($this->key('failed'));
    }

    private function key(string $suffix): string
    {
        return "retell:{$this->id}:{$suffix}";
    }
}
