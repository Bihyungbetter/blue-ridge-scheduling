<?php

namespace App\Retell;

/**
 * The Retell LLM `general_tools` for this app, generated from
 * config/scheduling.php so the enums never drift from the real rules.
 * Print with: php artisan retell:tools
 */
class ToolDefinitions
{
    /**
     * @param  'voice'|'sms'  $channel  SMS agents get the scheduling tools only
     *                                  (no hang-up or call transfer in a text chat).
     */
    public static function all(string $functionsUrl, string $channel = 'voice'): array
    {
        $departments = array_keys(config('scheduling.departments'));
        $types = collect(config('scheduling.departments'))
            ->flatMap(fn ($dept) => array_keys($dept['appointment_types']))
            ->unique()->values()->all();

        $tools = [
            self::custom($functionsUrl, 'verify_patient',
                'Verify the caller is the patient before discussing or changing any appointment. Required before book, list, cancel or reschedule.',
                [
                    'first_name' => ['type' => 'string', 'description' => "Patient's first name"],
                    'last_name' => ['type' => 'string', 'description' => "Patient's last name"],
                    'date_of_birth' => ['type' => 'string', 'description' => 'Date of birth as YYYY-MM-DD'],
                ],
                ['first_name', 'last_name', 'date_of_birth'],
            ),
            self::custom($functionsUrl, 'find_available_slots',
                'Find the next open appointment times. Does not require verification.',
                [
                    'department' => ['type' => 'string', 'enum' => $departments, 'description' => 'Department key'],
                    'appointment_type' => ['type' => 'string', 'enum' => $types, 'description' => 'Appointment type key offered by that department'],
                    'preferred_date' => ['type' => 'string', 'description' => 'Optional earliest date as YYYY-MM-DD'],
                ],
                ['department', 'appointment_type'],
                'Let me check the schedule.',
            ),
            self::custom($functionsUrl, 'book_appointment',
                'Book a time the patient chose from find_available_slots, after they confirm it.',
                [
                    'department' => ['type' => 'string', 'enum' => $departments],
                    'appointment_type' => ['type' => 'string', 'enum' => $types],
                    'start_time' => ['type' => 'string', 'description' => 'The start_time value exactly as returned by find_available_slots'],
                ],
                ['department', 'appointment_type', 'start_time'],
                'One moment while I book that.',
            ),
            self::custom($functionsUrl, 'list_appointments',
                "List the verified patient's upcoming appointments.",
                [],
                [],
            ),
            self::custom($functionsUrl, 'cancel_appointment',
                'Cancel one of the verified patient\'s appointments after they confirm.',
                ['appointment_id' => ['type' => 'integer', 'description' => 'appointment_id from list_appointments']],
                ['appointment_id'],
            ),
            self::custom($functionsUrl, 'reschedule_appointment',
                'Move one of the verified patient\'s appointments to a new time from find_available_slots.',
                [
                    'appointment_id' => ['type' => 'integer', 'description' => 'appointment_id from list_appointments'],
                    'new_start_time' => ['type' => 'string', 'description' => 'start_time exactly as returned by find_available_slots'],
                ],
                ['appointment_id', 'new_start_time'],
                'One moment while I move that.',
            ),
        ];

        if ($channel === 'sms') {
            return $tools;
        }

        $tools[] = [
            'type' => 'end_call',
            'name' => 'end_call',
            'description' => 'End the call once the patient has nothing else to schedule.',
        ];

        if (filled($number = config('scheduling.transfer_number'))) {
            $tools[] = [
                'type' => 'transfer_call',
                'name' => 'transfer_to_staff',
                'description' => 'Transfer to human scheduling staff when the patient asks for a person, verification fails, the request is urgent or clinical, or the AI cannot help.',
                'transfer_destination' => ['type' => 'predefined', 'number' => $number],
                'transfer_option' => ['type' => 'cold_transfer'],
                'speak_during_execution' => true,
                'execution_message_description' => 'Tell the patient you are transferring them to the scheduling team.',
            ];
        }

        return $tools;
    }

    /**
     * Plain-text department list for the {{departments}} dynamic variable.
     */
    public static function departmentSummary(): string
    {
        return collect(config('scheduling.departments'))
            ->map(function (array $dept, string $key) {
                $types = collect($dept['appointment_types'])
                    ->map(fn ($type, $typeKey) => "{$typeKey} ({$type['label']})")
                    ->implode(', ');

                return "{$key} = {$dept['name']}: {$types}";
            })
            ->implode('; ');
    }

    private static function custom(string $url, string $name, string $description, array $properties, array $required, ?string $speakDuring = null): array
    {
        return array_filter([
            'type' => 'custom',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'method' => 'POST',
            'parameters' => [
                'type' => 'object',
                'properties' => (object) $properties,
                'required' => $required,
            ],
            'args_at_root' => false,
            'speak_during_execution' => $speakDuring !== null,
            'execution_message_description' => $speakDuring,
            'speak_after_execution' => true,
            'timeout_ms' => 10000,
        ], fn ($value) => $value !== null);
    }
}
