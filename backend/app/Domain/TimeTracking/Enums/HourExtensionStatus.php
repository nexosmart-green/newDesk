<?php

namespace App\Domain\TimeTracking\Enums;

/** RF-05.08 */
enum HourExtensionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
