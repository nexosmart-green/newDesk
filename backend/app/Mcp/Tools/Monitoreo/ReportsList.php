<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Queries\ProjectLookup;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Queries\ReportSearch;
use Carbon\CarbonImmutable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('reports_list')]
#[Description('Lista reportes de problemas, el más nuevo primero. Sin project trae los últimos de tus proyectos (all: true, de todos).')]
#[IsReadOnly]
final class ReportsList extends MonitoreoTool
{
    protected function properties(): array
    {
        return [
            'project' => self::PROJECT,
            'client' => ['type' => 'string', 'description' => 'Parte del nombre del cliente.'],
            'status' => ['type' => 'string', 'enum' => Report::STATUSES, 'description' => 'received = Recibido, in_review = En revisión, resolved = Resuelto.'],
            'urgency' => ['type' => 'string', 'enum' => array_keys(Report::URGENCY_LABELS)],
            'unread' => ['type' => 'boolean', 'description' => 'Solo los que tienen mensajes del cliente sin leer por el equipo.'],
            'since' => ['type' => 'string', 'description' => 'Desde esta fecha (YYYY-MM-DD).'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'description' => 'Por página (20 por defecto).'],
            'page' => ['type' => 'integer', 'minimum' => 1],
            'all' => self::ALL,
        ];
    }

    public function answer(Request $request, ProjectLookup $lookup, ReportSearch $search): Response
    {
        $status = (string) $request->get('status');
        if ($status !== '' && ! in_array($status, Report::STATUSES, true)) {
            throw new BusinessRuleViolation('status tiene que ser received, in_review o resolved.', 'invalid_status');
        }
        $limit = (int) $request->get('limit') > 0 ? min((int) $request->get('limit'), 50) : 20;
        $page = max((int) $request->get('page'), 1);
        $since = null;
        if (($day = (string) $request->get('since')) !== '') {
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new BusinessRuleViolation('since tiene que ser una fecha YYYY-MM-DD.', 'invalid_since');
            }
            $since = CarbonImmutable::parse("{$day} 00:00:00", 'UTC');
        }
        $project = trim((string) $request->get('project'));
        $projectIds = match (true) {
            $project !== '' => [$lookup->find($project)->id],
            (bool) $request->get('all') => null,
            default => $lookup->scoped(self::user(), false)->modelKeys(),
        };

        [$reports, $total] = $search->find($projectIds, trim((string) $request->get('client')), $status, (string) $request->get('urgency'),
            (bool) $request->get('unread'), $since, $limit, ($page - 1) * $limit);
        $rows = $reports->map(function (Report $report) {
            $row = $report->toRowPayload();
            $row['description'] = self::untrusted($row['description']);

            return $row;
        });

        return self::json(['reports' => $rows->values()->all(), 'total' => $total, 'page' => $page, 'per_page' => $limit]);
    }
}
