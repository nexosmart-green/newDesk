<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    /** RF-01.10: deactivation, never deletion. */
    case Inactive = 'inactive';
}
