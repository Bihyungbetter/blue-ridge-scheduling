<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Retell\AgentContext;
use App\Retell\RetellClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Staff tool for trying the Retell agent: call or text a phone, or talk to
 * it in the browser. Only call numbers that have agreed to it (your own,
 * for testing); AI-voice calls to patients need prior consent (TCPA).
 */
class AgentTestController extends Controller
{
    public function __construct(private RetellClient $retell, private AgentContext $context) {}

    public function show(): Response
    {
        return Inertia::render('agent-test', [
            'setup' => [
                'apiKey' => $this->retell->hasApiKey(),
                'voiceAgent' => filled(config('services.retell.voice_agent_id')),
                'phoneNumber' => filled(config('services.retell.from_number')),
                'smsEnabled' => (bool) config('services.retell.sms_enabled') && filled(config('services.retell.sms_agent_id')),
            ],
        ]);
    }

    public function call(Request $request): RedirectResponse
    {
        $to = $this->phone($request);

        return $this->attempt($request, 'test_call', fn () => $this->retell->createPhoneCall(
            $to,
            $this->context->dynamicVariables(),
            ['purpose' => 'staff_test', 'user_id' => $request->user()->id],
        ), 'Calling now. Your phone should ring in a few seconds.');
    }

    public function text(Request $request): RedirectResponse
    {
        if (! config('services.retell.sms_enabled')) {
            return back()->withErrors(['text' => 'Texting is off until the number\'s A2P 10DLC registration is approved (set RETELL_SMS_ENABLED=true then).']);
        }

        $to = $this->phone($request, 'text_phone');

        return $this->attempt($request, 'test_text', fn () => $this->retell->createSmsChat(
            $to,
            $this->context->dynamicVariables(),
            ['purpose' => 'staff_test', 'user_id' => $request->user()->id],
        ), 'Text sent. Reply to it to keep chatting with the agent.', 'text');
    }

    /**
     * JSON for retell-client-js-sdk: the API key stays on the server.
     */
    public function webCall(Request $request): JsonResponse
    {
        try {
            $call = $this->retell->createWebCall(
                $this->context->dynamicVariables(),
                ['purpose' => 'staff_test', 'user_id' => $request->user()->id],
            );
        } catch (RequestException|ConnectionException|RuntimeException $e) {
            AuditLog::record('test_web_call', 'staff', 'error', ['user_id' => $request->user()->id]);

            return response()->json(['message' => $this->describe($e)], 422);
        }

        AuditLog::record('test_web_call', 'staff', 'ok', ['user_id' => $request->user()->id, 'conversation_id' => $call['call_id'] ?? null]);

        return response()->json(array_intersect_key($call, array_flip(['call_id', 'access_token', 'transport', 'url', 'ice_servers'])));
    }

    /**
     * Accepts "(828) 555-0100", "828-555-0100", "18285550100" or "+18285550100"
     * and returns US E.164.
     */
    private function phone(Request $request, string $field = 'phone'): string
    {
        $request->validate([$field => 'required|string|max:32']);

        $digits = preg_replace('/\D/', '', $request->input($field));
        $digits = strlen($digits) === 11 && str_starts_with($digits, '1') ? substr($digits, 1) : $digits;

        if (! preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $digits)) {
            throw ValidationException::withMessages([$field => 'Enter a 10-digit US phone number.']);
        }

        return '+1'.$digits;
    }

    private function attempt(Request $request, string $action, callable $send, string $success, string $errorKey = 'call'): RedirectResponse
    {
        try {
            $result = $send();
        } catch (RequestException|ConnectionException|RuntimeException $e) {
            // Not reported: Retell's error body can echo the phone number.
            AuditLog::record($action, 'staff', 'error', ['user_id' => $request->user()->id]);

            return back()->withErrors([$errorKey => $this->describe($e)]);
        }

        AuditLog::record($action, 'staff', 'ok', [
            'user_id' => $request->user()->id,
            'conversation_id' => $result['call_id'] ?? $result['chat_id'] ?? null,
        ]);

        return back()->with('status', $success);
    }

    private function describe(\Throwable $e): string
    {
        if ($e instanceof RequestException) {
            $message = $e->response->json('message') ?? 'Retell rejected the request.';

            return "Retell error ({$e->response->status()}): {$message}";
        }

        return $e instanceof ConnectionException ? 'Could not reach Retell. Check the internet connection.' : $e->getMessage();
    }
}
