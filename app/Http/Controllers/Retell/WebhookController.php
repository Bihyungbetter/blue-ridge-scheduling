<?php

namespace App\Http\Controllers\Retell;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Call and chat lifecycle events (POST /retell/webhook).
 *
 * The payload carries the full transcript. Only non-PHI metadata and the
 * post-call summary are kept; the transcript and raw body are never stored
 * or logged. Upserting by conversation ID makes Retell's retries harmless.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $event = (string) $request->input('event');
        $data = $request->input('call') ?? $request->input('chat');

        $id = is_array($data) ? ($data['call_id'] ?? $data['chat_id'] ?? null) : null;

        if ($event === '' || $id === null) {
            return response()->noContent();
        }

        $analysis = $data['call_analysis'] ?? $data['chat_analysis'] ?? [];

        $fields = array_filter([
            'direction' => $data['direction'] ?? null,
            'disconnection_reason' => $data['disconnection_reason'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            // Carbon 3 defaults to UTC here, and Eloquent stores wall-clock time
            // without converting, so pin the app timezone.
            'started_at' => isset($data['start_timestamp']) ? Carbon::createFromTimestampMs($data['start_timestamp'], config('app.timezone')) : null,
            'ended_at' => isset($data['end_timestamp']) ? Carbon::createFromTimestampMs($data['end_timestamp'], config('app.timezone')) : null,
            'summary' => $analysis['call_summary'] ?? $analysis['chat_summary'] ?? null,
            'user_sentiment' => $analysis['user_sentiment'] ?? null,
            'successful' => $analysis['call_successful'] ?? $analysis['chat_successful'] ?? null,
        ], fn ($value) => $value !== null);

        if (str_starts_with($event, 'transfer_') || ($data['disconnection_reason'] ?? null) === 'call_transfer') {
            $fields['transferred'] = true;
        }

        Conversation::updateOrCreate(
            ['conversation_id' => $id],
            [
                'channel' => isset($data['chat_id']) ? 'sms' : 'voice',
                'last_event' => $event,
                ...$fields,
            ],
        );

        return response()->noContent();
    }
}
