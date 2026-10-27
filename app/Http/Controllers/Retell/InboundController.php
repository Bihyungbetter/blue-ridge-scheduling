<?php

namespace App\Http\Controllers\Retell;

use App\Http\Controllers\Controller;
use App\Retell\AgentContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound call / SMS webhook (POST /retell/inbound). Set it as the phone
 * number's inbound_webhook_url and inbound_sms_webhook_url.
 *
 * Gives the agent fresh context ({{current_time}}, {{departments}}, ...).
 * It deliberately does not look the caller up by phone number: nothing
 * patient-specific is shared until verify_patient succeeds.
 */
class InboundController extends Controller
{
    public function __invoke(Request $request, AgentContext $context): JsonResponse
    {
        $key = $request->input('event') === 'chat_inbound' ? 'chat_inbound' : 'call_inbound';

        return response()->json([
            $key => ['dynamic_variables' => $context->dynamicVariables()],
        ]);
    }
}
