<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Support\Events\ReportChanged;
use App\Domain\Support\Models\ReportMessage;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('reports_mark_read')]
#[Description('Marca como leídos los mensajes del cliente en ese reporte (saca la marca de mensaje nuevo del equipo).')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class ReportsMarkRead extends MonitoreoTool
{
    protected function properties(): array
    {
        return ['id' => self::ID];
    }

    protected function required(): array
    {
        return ['id'];
    }

    /** As opening the report in the web does; only the team's mark changes, so only the team hears it. */
    public function answer(Request $request): Response
    {
        $report = self::report($request->get('id'));
        $report->markSeen(ReportMessage::TEAM);
        ReportChanged::send($report, ReportChanged::REPORT, teamOnly: true);

        return Response::text("Listo: el reporte #{$report->id} quedó leído para el equipo.");
    }
}
