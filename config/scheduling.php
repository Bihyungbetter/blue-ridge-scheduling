<?php

/*
|--------------------------------------------------------------------------
| Scheduling rules
|--------------------------------------------------------------------------
|
| All scheduling logistics live here and in App\Scheduling\SchedulingService.
| The AI agent never decides availability; it only calls the Retell
| function endpoints, which run these rules.
|
| PLACEHOLDER DATA: every department, hour, capacity and phone number below
| is an example. Replace it with the real clinic rules before go-live.
|
*/

return [

    'timezone' => 'America/New_York',

    // Earliest bookable slot, measured from "now".
    'lead_time_minutes' => 120,

    // How far ahead patients may book.
    'horizon_days' => 60,

    // Patients can't cancel/reschedule through the AI inside this window.
    'change_cutoff_hours' => 24,

    // Maximum slots returned to the agent per search (read aloud on calls).
    'max_slots_returned' => 5,

    // Failed identity checks allowed per call/text conversation.
    'max_verification_attempts' => 3,

    // Clinic closures (Y-m-d). PLACEHOLDER.
    'holidays' => [
        '2026-11-26',
        '2026-12-25',
        '2027-01-01',
    ],

    // Human scheduling line used by the transfer_call tool (E.164). PLACEHOLDER.
    'transfer_number' => env('SCHEDULING_TRANSFER_NUMBER'),

    /*
    | Hours are keyed by ISO weekday (1 = Monday ... 7 = Sunday).
    | `capacity` = appointments that may overlap (e.g. providers on shift).
    | `slot_minutes` = grid that appointment start times snap to.
    */
    'departments' => [

        'primary_care' => [
            'name' => 'Primary Care',
            'capacity' => 2,
            'slot_minutes' => 20,
            'hours' => [
                1 => ['08:00', '17:00'],
                2 => ['08:00', '17:00'],
                3 => ['08:00', '17:00'],
                4 => ['08:00', '17:00'],
                5 => ['08:00', '12:00'],
            ],
            'appointment_types' => [
                'new_patient' => ['label' => 'New patient visit', 'minutes' => 40],
                'follow_up' => ['label' => 'Follow-up visit', 'minutes' => 20],
                'annual_physical' => ['label' => 'Annual physical', 'minutes' => 40],
            ],
        ],

        'cardiology' => [
            'name' => 'Cardiology',
            'capacity' => 1,
            'slot_minutes' => 30,
            'hours' => [
                2 => ['09:00', '16:00'],
                4 => ['09:00', '16:00'],
            ],
            'appointment_types' => [
                'consult' => ['label' => 'Cardiology consult', 'minutes' => 60],
                'follow_up' => ['label' => 'Cardiology follow-up', 'minutes' => 30],
            ],
        ],

        'imaging' => [
            'name' => 'Imaging',
            'capacity' => 1,
            'slot_minutes' => 15,
            'hours' => [
                1 => ['07:30', '18:00'],
                2 => ['07:30', '18:00'],
                3 => ['07:30', '18:00'],
                4 => ['07:30', '18:00'],
                5 => ['07:30', '18:00'],
                6 => ['08:00', '12:00'],
            ],
            'appointment_types' => [
                'xray' => ['label' => 'X-ray', 'minutes' => 15],
                'ultrasound' => ['label' => 'Ultrasound', 'minutes' => 45],
            ],
        ],

    ],

];
