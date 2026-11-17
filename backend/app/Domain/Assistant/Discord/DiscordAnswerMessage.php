<?php

namespace App\Domain\Assistant\Discord;

use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Assistant\Enums\ProposalStatus;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;

/**
 * An assistant answer as a Discord message: the text, how each proposal stands, and a Confirmar / Cancelar row
 * for each one still pending. Rebuilt after every click, so the message always shows the current state.
 */
final class DiscordAnswerMessage
{
    /** Discord: 2000 characters per message. */
    private const MAX_CONTENT = 1900;

    /** Discord: at most 5 action rows. */
    private const MAX_ROWS = 5;

    public const CONFIRM = 'confirm';

    public const CANCEL = 'cancel';

    private const ENDED = [
        'confirmed' => 'Hecho',
        'cancelled' => 'Cancelada, no se cambió nada',
        'failed' => 'No se pudo hacer',
        'expired' => 'Venció, pedíselo de nuevo',
    ];

    /**
     * The proposals always fit (they say what a button confirms); only the answer's text is clipped to make room.
     *
     * @return array{content: string, components: list<array<string, mixed>>}
     */
    public static function of(AssistantMessage $answer): array
    {
        $answer->loadMissing('proposals');
        $lines = [];
        $rows = [];
        $withoutButton = 0;

        foreach ($answer->proposals as $proposal) {
            $pending = $proposal->status === ProposalStatus::Pending && ! $proposal->isExpired();
            if ($pending && count($rows) >= self::MAX_ROWS) {
                $withoutButton++;

                continue;
            }
            $lines[] = $pending
                ? "**Para confirmar:** {$proposal->summary}"
                : '**'.self::ENDED[$proposal->isExpired() && $proposal->status === ProposalStatus::Pending ? 'expired' : $proposal->status->value].":** {$proposal->summary}"
                    .($proposal->error ? " ({$proposal->error})" : '');
            if ($pending) {
                $rows[] = self::buttons($proposal);
            }
        }
        if ($withoutButton > 0) {
            $lines[] = "Hay {$withoutButton} propuesta(s) más: confirmalas en newDesk → Asistente.";
        }

        $proposals = self::clip(implode("\n\n", $lines));
        $room = self::MAX_CONTENT - mb_strlen($proposals) - 2;
        $text = $answer->status === MessageStatus::Failed ? (string) $answer->error : (string) $answer->content;
        $text = $room <= 1 ? '' : (mb_strlen($text) > $room ? mb_substr($text, 0, $room - 1).'…' : $text);

        return ['content' => implode("\n\n", array_filter([$text, $proposals])), 'components' => $rows];
    }

    public static function customId(string $action, int $proposalId): string
    {
        return "assistant:{$action}:{$proposalId}";
    }

    /** @return array{action: string, proposal: int}|null */
    public static function parseCustomId(?string $customId): ?array
    {
        return preg_match('/^assistant:(confirm|cancel):(\d+)$/', (string) $customId, $m) ? ['action' => $m[1], 'proposal' => (int) $m[2]] : null;
    }

    public static function clip(string $content): string
    {
        return mb_strlen($content) > self::MAX_CONTENT ? mb_substr($content, 0, self::MAX_CONTENT - 1).'…' : $content;
    }

    /** @return array<string, mixed> */
    private static function buttons(AssistantProposal $proposal): array
    {
        return ['type' => 1, 'components' => [
            ['type' => 2, 'style' => 1, 'label' => 'Confirmar #'.$proposal->id, 'custom_id' => self::customId(self::CONFIRM, $proposal->id)],
            ['type' => 2, 'style' => 2, 'label' => 'Cancelar', 'custom_id' => self::customId(self::CANCEL, $proposal->id)],
        ]];
    }
}
