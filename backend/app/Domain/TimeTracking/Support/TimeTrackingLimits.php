<?php

namespace App\Domain\TimeTracking\Support;

/**
 * Input limits shared by the web (FormRequests) and MCP tools, so both reject the same thing.
 */
final class TimeTrackingLimits
{
    /** What-I-did comment of a time entry (stop, switch, manual, pending). */
    public const ENTRY_COMMENT_MAX = 5000;

    /** One allocation movement: ±10 000 h. MCP takes minutes, so ±600 000. */
    public const ALLOCATION_SECONDS_MAX = 36_000_000;

    public const ALLOCATION_REASON_MAX = 255;

    public const ALLOCATION_REFERENCE_MAX = 120;

    public const EXTENSION_MESSAGE_MAX = 2000;
}
