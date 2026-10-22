<?php

namespace App\Retell;

/**
 * Port of Retell's official SDK webhook auth (retell-typescript-sdk
 * src/lib/webhook_auth.ts). Header value: "v=<unix ms>,d=<hex hmac>", where
 * the HMAC-SHA256 is over raw body + timestamp digits, keyed with the API key.
 */
class RetellSignature
{
    public const HEADER = 'X-Retell-Signature';

    public const TOLERANCE_MS = 5 * 60 * 1000;

    public static function sign(string $rawBody, string $apiKey, ?int $timestampMs = null): string
    {
        $timestampMs ??= self::nowMs();

        return 'v='.$timestampMs.',d='.hash_hmac('sha256', $rawBody.$timestampMs, $apiKey);
    }

    public static function verify(string $rawBody, string $apiKey, string $signature, ?int $nowMs = null): bool
    {
        if (! preg_match('/^v=(\d+),d=([0-9a-f]{64})$/i', $signature, $matches)) {
            return false;
        }

        $timestampMs = (int) $matches[1];

        if (abs(($nowMs ?? self::nowMs()) - $timestampMs) > self::TOLERANCE_MS) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody.$timestampMs, $apiKey);

        return hash_equals($expected, strtolower($matches[2]));
    }

    private static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
