<?php

namespace App\Domain\Assistant\Enums;

/** RF-13.02: a write the assistant proposed, waiting for the person who asked. */
enum ProposalStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    /** Confirmed, but the action refused it (rule or permission changed since it was proposed). */
    case Failed = 'failed';
    case Expired = 'expired';
}
