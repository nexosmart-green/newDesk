<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Shared\Text;
use App\Domain\Support\Actions\PostReportMessage;
use App\Domain\Support\Models\ReportMessage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** The client sees it at once, so it is annotated for an MCP client to ask before each call (D-092). */
#[Name('reports_reply')]
#[Title('Responder en el chat del reporte (lo ve el cliente)')]
#[Description('Responde en el chat del reporte. El cliente lo ve al instante en su portal, firmado como "Equipo nexosmart". Con internal: true queda como nota interna: solo la ve el equipo (ideal para dejar un diagnóstico).')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld]
final class ReportsReply extends MonitoreoTool
{
    protected function properties(): array
    {
        return [
            'id' => self::ID,
            'body' => ['type' => 'string', 'description' => 'El mensaje (hasta 4000 caracteres).'],
            'internal' => ['type' => 'boolean', 'description' => 'true = nota interna, el cliente no la ve.'],
        ];
    }

    protected function required(): array
    {
        return ['id', 'body'];
    }

    public function answer(Request $request, PostReportMessage $post): Response
    {
        $body = Text::clean((string) $request->get('body'));
        if ($body === '' || mb_strlen($body) > 4000) {
            throw new BusinessRuleViolation('El mensaje tiene que tener entre 1 y 4000 caracteres.', 'invalid_body');
        }
        $report = self::report($request->get('id'));
        $internal = (bool) $request->get('internal');
        $message = $post($report, self::user(), $body, [], $internal);

        if ($internal) {
            return Response::text("Listo: dejé una nota interna en el reporte #{$report->id} (mensaje {$message->id}). El cliente no la ve.");
        }

        return Response::text(sprintf('Listo: respondí en el reporte #%d (mensaje %d). El cliente lo ve como "%s".', $report->id, $message->id, ReportMessage::TEAM_AUTHOR));
    }
}
