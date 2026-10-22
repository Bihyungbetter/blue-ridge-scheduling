<?php

namespace App\Retell;

use App\Scheduling\SchedulingService;

/**
 * The {{dynamic_variables}} every conversation starts with. Inbound calls get
 * them from the inbound webhook; outbound and web calls must pass them
 * explicitly, so all three share this.
 */
class AgentContext
{
    public function __construct(private SchedulingService $scheduling) {}

    /**
     * @return array<string, string> Retell requires string values.
     */
    public function dynamicVariables(): array
    {
        return [
            'clinic_name' => (string) config('app.name'),
            'current_time' => $this->scheduling->spoken($this->scheduling->now()),
            'departments' => ToolDefinitions::departmentSummary(),
            'transfer_available' => filled(config('scheduling.transfer_number')) ? 'yes' : 'no',
        ];
    }
}
