<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Support\Models\ReportMessage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('reports_get')]
#[Description('Un reporte completo: descripción, urgencia, dónde, cliente y proyecto, chequeo en vivo, problemas abiertos, errores del proyecto al reportar, foto de los servers de ese momento (métricas, procesos, servicios y alertas), navegador y el chat entero. Las imágenes se ven con reports_images.')]
#[IsReadOnly]
final class ReportsGet extends MonitoreoTool
{
    private const BROWSER_FIELDS = ['user_agent', 'page_url', 'screen', 'timezone', 'language'];

    protected function properties(): array
    {
        return ['id' => self::ID];
    }

    protected function required(): array
    {
        return ['id'];
    }

    public function answer(Request $request): Response
    {
        $report = self::report($request->get('id'));
        $chat = $report->chat(withInternal: true)->map(fn (ReportMessage $message) => [
            'id' => $message->id,
            'from' => $message->author_kind,
            'author' => $message->author,
            'body' => $message->author_kind === ReportMessage::CLIENT ? self::untrusted($message->body) : $message->body,
            ...($message->internal ? ['internal' => true] : []),
            'created_at' => $message->created_at->toJSON(),
            'attachment_ids' => $message->attachments->modelKeys(),
        ]);

        // What the client typed or its browser sent, and its app's errors (NV-06).
        $detail = $report->load(['client', 'project'])->toDetailPayload([]);
        $detail['messages'] = null;
        $detail['description'] = self::untrusted($report->description);
        $browser = (array) $report->browser;
        $detail['browser'] = [];
        foreach (self::BROWSER_FIELDS as $field) {
            $detail['browser'][$field] = self::untrusted((string) ($browser[$field] ?? ''));
        }
        if ($report->recent_errors !== null) {
            $errors = json_decode((string) json_encode($report->recent_errors), true);
            $errors['items'] = self::untrustedErrors($errors['items'] ?? []);
            $detail['recent_errors'] = $errors;
        }

        return self::json(['report' => $detail, 'has_screenshot' => $report->screenshot_path !== null, 'chat' => $chat->values()->all()]);
    }
}
