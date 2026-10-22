<?php

namespace App\Scheduling;

use RuntimeException;

/**
 * A scheduling rule was violated. The message is safe to read back to the
 * patient (the AI agent speaks it), so never put PHI in it.
 */
class SchedulingException extends RuntimeException
{
}
