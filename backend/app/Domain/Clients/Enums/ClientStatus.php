<?php

namespace App\Domain\Clients\Enums;

enum ClientStatus: string
{
    case Active = 'active';
    /** CA-02.01: not offered when creating new projects. */
    case Inactive = 'inactive';
}
