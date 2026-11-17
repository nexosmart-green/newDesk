<?php

namespace App\Console\Commands;

use App\Domain\Assistant\Contracts\DiscordInteractionClient;
use Illuminate\Console\Command;

/**
 * RF-14.08: registers /newdesk in the company's Discord server (monitoreo's guild), with monitoreo's bot token.
 * Run once after setting DISCORD_APPLICATION_ID and DISCORD_PUBLIC_KEY, and again whenever the command changes.
 */
class AssistantDiscordCommands extends Command
{
    /** SUB_COMMAND = 1, STRING = 3 (https://discord.com/developers/docs/interactions/application-commands). */
    public const COMMANDS = [[
        'name' => 'newdesk',
        'description' => 'Asistente de newDesk',
        'options' => [
            ['type' => 1, 'name' => 'pedir', 'description' => 'Pedile un reporte, un resumen o un cambio', 'options' => [
                ['type' => 3, 'name' => 'pedido', 'description' => 'Qué necesitás, en tus palabras', 'required' => true, 'max_length' => 2000],
            ]],
            ['type' => 1, 'name' => 'vincular', 'description' => 'Conectá tu cuenta con el código de newDesk → Asistente', 'options' => [
                ['type' => 3, 'name' => 'codigo', 'description' => 'El código que te dio newDesk', 'required' => true, 'max_length' => 16],
            ]],
        ],
    ]];

    protected $signature = 'assistant:discord-commands';

    protected $description = 'Register the /newdesk slash command in the Discord server';

    public function handle(DiscordInteractionClient $discord): int
    {
        foreach (['newdesk.assistant.discord.application_id', 'newdesk.monitoring.discord.bot_token', 'newdesk.monitoring.discord.guild_id'] as $key) {
            if (blank(config($key))) {
                $this->error("Falta configurar {$key} en el .env.");

                return self::FAILURE;
            }
        }

        $discord->registerCommands(self::COMMANDS);
        $this->info('Listo: /newdesk quedó registrado en el servidor.');

        return self::SUCCESS;
    }
}
