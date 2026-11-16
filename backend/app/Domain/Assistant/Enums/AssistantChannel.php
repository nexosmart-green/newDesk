<?php

namespace App\Domain\Assistant\Enums;

/** Where a person talks to the assistant (RF-13): one core, several channels. */
enum AssistantChannel: string
{
    case Web = 'web';
    case Discord = 'discord';
}
