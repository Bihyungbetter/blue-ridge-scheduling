<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Scheduling\SchedulingService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SchedulingService $scheduling): Response
    {
        $today = $scheduling->now()->startOfDay();
        $range = [$today->format('Y-m-d H:i:s'), $today->endOfDay()->format('Y-m-d H:i:s')];

        $todaysAppointments = Appointment::active()
            ->with('patient:id,first_name,last_name')
            ->whereBetween('starts_at', $range)
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('dashboard', [
            'stats' => [
                'appointmentsToday' => $todaysAppointments->count(),
                'aiBookedThisWeek' => Appointment::whereIn('source', ['voice', 'sms'])
                    ->where('created_at', '>=', $today->startOfWeek())
                    ->count(),
                'conversationsToday' => Conversation::where('created_at', '>=', $today)->count(),
                'transfersToday' => Conversation::where('created_at', '>=', $today)->where('transferred', true)->count(),
            ],
            'schedule' => $todaysAppointments->map(fn (Appointment $a) => AppointmentController::present($a)),
            'recentConversations' => Conversation::latest()->limit(5)->get()->map(fn (Conversation $c) => ConversationController::present($c)),
        ]);
    }
}
