<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Scheduling\SchedulingException;
use App\Scheduling\SchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request, SchedulingService $scheduling): Response
    {
        $filters = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'department' => 'nullable|string',
            'status' => 'nullable|in:scheduled,cancelled,all',
        ]);

        $date = filled($filters['date'] ?? null) ? $scheduling->parseDate($filters['date']) : $scheduling->now()->startOfDay();
        $department = $filters['department'] ?? 'all';
        $status = $filters['status'] ?? 'scheduled';

        $appointments = Appointment::query()
            ->with('patient:id,first_name,last_name')
            ->whereBetween('starts_at', [$date->format('Y-m-d H:i:s'), $date->endOfDay()->format('Y-m-d H:i:s')])
            ->when($department !== 'all', fn ($q) => $q->where('department', $department))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('starts_at')
            ->get();

        AuditLog::record('view_schedule', 'staff', 'ok', ['user_id' => $request->user()->id]);

        return Inertia::render('appointments/index', [
            'appointments' => $appointments->map(fn (Appointment $a) => self::present($a)),
            'filters' => ['date' => $date->format('Y-m-d'), 'department' => $department, 'status' => $status],
            'departments' => collect(config('scheduling.departments'))
                ->map(fn ($dept, $key) => ['value' => $key, 'label' => $dept['name']])
                ->values(),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, SchedulingService $scheduling): RedirectResponse
    {
        try {
            $scheduling->cancel($appointment);
        } catch (SchedulingException $e) {
            return back()->withErrors(['appointment' => $e->getMessage()]);
        }

        AuditLog::record('cancel_appointment', 'staff', 'ok', [
            'user_id' => $request->user()->id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
        ]);

        return back();
    }

    public static function present(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'patient' => $appointment->patient?->fullName(),
            'department' => $appointment->departmentName(),
            'type' => $appointment->typeLabel(),
            'starts_at' => $appointment->starts_at->format('Y-m-d\TH:i'),
            'time' => $appointment->starts_at->format('g:i A'),
            'status' => $appointment->status,
            'source' => $appointment->source,
        ];
    }
}
