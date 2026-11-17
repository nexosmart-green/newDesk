<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Queries\ProjectLookup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('alerts_list')]
#[Description('Alertas abiertas de los servers (caídas, CPU, disco, procesos, SSL, sitios…), la más vieja primero. Sin project trae las de los servers de tus proyectos (all: true, todas).')]
#[IsReadOnly]
final class AlertsList extends MonitoreoTool
{
    protected function properties(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Solo las de los servers de este proyecto (id, slug o nombre).'],
            'server' => ['type' => 'string', 'description' => 'Parte del nombre del server.'],
            'severity' => ['type' => 'string', 'enum' => [AlertRules::CRITICAL, AlertRules::WARNING]],
            'all' => self::ALL,
        ];
    }

    public function answer(Request $request, ProjectLookup $lookup): Response
    {
        $severity = (string) $request->get('severity');
        if ($severity !== '' && ! in_array($severity, [AlertRules::CRITICAL, AlertRules::WARNING], true)) {
            throw new BusinessRuleViolation('severity tiene que ser critical o warning.', 'invalid_severity');
        }
        $project = trim((string) $request->get('project'));
        $servers = match (true) {
            $project !== '' => $lookup->serverIds([$lookup->find($project)->id]),
            (bool) $request->get('all') => null,
            default => $lookup->serverIds($lookup->scoped(self::user(), false)->modelKeys()),
        };
        $server = mb_strtolower(trim((string) $request->get('server')));

        $alerts = Alert::query()->with('server:id,name')->where('state', Alert::FIRING)->orderBy('first_seen_at')->orderBy('id')->get()
            ->filter(fn (Alert $alert) => ($servers === null || in_array((int) $alert->server_id, $servers, true))
                && ($severity === '' || $alert->severity === $severity)
                && ($server === '' || str_contains(mb_strtolower($alert->server->name), $server)))
            ->map(fn (Alert $alert) => $alert->toPayload())->values()->all();

        return self::json(['alerts' => $alerts, 'total' => count($alerts)]);
    }
}
