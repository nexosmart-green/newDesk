<?php

namespace App\Domain\Assistant\Enums;

/**
 * Models the Admin can choose for the assistant (RF-14.10), passed to `claude --model`.
 */
enum AssistantModel: string
{
    case Opus = 'claude-opus-5-5';
    case Sonnet = 'claude-sonnet-5-5';
    case Haiku = 'claude-haiku-5-5';

    public function label(): string
    {
        return match ($this) {
            self::Opus => 'Opus (el más capaz)',
            self::Sonnet => 'Sonnet (equilibrado)',
            self::Haiku => 'Haiku (el más rápido y barato)',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
