<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $channel = $request->validate(['channel' => 'nullable|in:voice,sms,all'])['channel'] ?? 'all';

        $conversations = Conversation::query()
            ->with('patient:id,first_name,last_name')
            ->when($channel !== 'all', fn ($q) => $q->where('channel', $channel))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Conversation $c) => self::present($c));

        return Inertia::render('conversations/index', [
            'conversations' => $conversations,
            'filters' => ['channel' => $channel],
        ]);
    }

    public static function present(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'channel' => $conversation->channel,
            'direction' => $conversation->direction,
            'patient' => $conversation->patient?->fullName(),
            'status' => $conversation->last_event,
            'summary' => $conversation->summary,
            'sentiment' => $conversation->user_sentiment,
            'successful' => $conversation->successful,
            'transferred' => $conversation->transferred,
            'disconnection_reason' => $conversation->disconnection_reason,
            'duration_seconds' => $conversation->duration_ms !== null ? intdiv($conversation->duration_ms, 1000) : null,
            'at' => ($conversation->started_at ?? $conversation->created_at)->timezone(config('scheduling.timezone'))->format('M j, g:i A'),
        ];
    }
}
