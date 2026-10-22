<?php

namespace App\Http\Controllers\Retell;

use App\Http\Controllers\Controller;
use App\Retell\ToolDefinitions;
use App\Scheduling\SchedulingService;
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
    public function __invoke(Request $request, SchedulingService $scheduling): JsonResponse
    {
        $key = $request->input('event') === 'chat_inbound' ? 'chat_inbound' : 'call_inbound';

        return response()->json([
            $key => [
                // Retell requires string values.
                'dynamic_variables' => [
                    'clinic_name' => (string) config('app.name'),
                    'current_time' => $scheduling->spoken($scheduling->now()),
                    'departments' => ToolDefinitions::departmentSummary(),
                    'transfer_available' => filled(config('scheduling.transfer_number')) ? 'yes' : 'no',
                ],
            ],
        ]);
    }
}
