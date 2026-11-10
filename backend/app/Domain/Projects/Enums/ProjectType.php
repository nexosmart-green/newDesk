<?php

namespace App\Domain\Projects\Enums;

/**
 * P-05: no invoicing. A project either has a hard hour bag or is internal (no cap, still tracked).
 */
enum ProjectType: string
{
    case Bag = 'bag';
    case Internal = 'internal';

    public function hasHourCap(): bool
    {
        return $this === self::Bag;
    }
}
