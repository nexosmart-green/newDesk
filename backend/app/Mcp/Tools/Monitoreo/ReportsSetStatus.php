<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\Actions\SetReportStatus;
use App\Domain\Support\Models\Report;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('reports_set_status')]
#[Title('Cambiar el estado del reporte (lo ve el cliente)')]
#[Description('Cambia el estado del reporte (el cliente lo ve en su portal).')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsOpenWorld]
final class ReportsSetStatus extends MonitoreoTool
{
    protected function properties(): array
    {
        return ['id' => self::ID, 'status' => ['type' => 'string', 'enum' => Report::STATUSES]];
    }

    protected function required(): array
    {
        return ['id', 'status'];
    }

    public function answer(Request $request, SetReportStatus $setStatus): Response
    {
        $status = (string) $request->get('status');
        if (! in_array($status, Report::STATUSES, true)) {
            throw new BusinessRuleViolation('status tiene que ser received, in_review o resolved.', 'invalid_status');
        }
        $report = self::report($request->get('id'));
        $setStatus($report, $status, self::user());

        return Response::text("Listo: el reporte #{$report->id} quedó en {$status}.");
    }
}
