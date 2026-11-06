<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\StatusPage\Queries\ProjectAdmin;
use App\Domain\StatusPage\Queries\ProjectLookup;
use App\Domain\StatusPage\Queries\StatusPage;
use Carbon\CarbonImmutable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('project_status')]
#[Description('Estado actual de un proyecto: estado general, cada componente (web, API, procesos, backups) con su último chequeo, latencia, código HTTP, SSL y server, las carpetas vinculadas y si el cliente está viendo el aviso de incidente. No hace un chequeo nuevo.')]
#[IsReadOnly]
final class ProjectStatus extends MonitoreoTool
{
    protected function properties(): array
    {
        return ['project' => self::PROJECT];
    }

    protected function required(): array
    {
        return ['project'];
    }

    public function answer(Request $request, ProjectLookup $lookup, ProjectAdmin $admin, StatusPage $page): Response
    {
        $project = $lookup->find((string) $request->get('project'));

        return self::json([
            'project' => $admin->show($project),
            'client_sees_incident_notice' => $page->incidentNotice($project, StatusPage::components($project), CarbonImmutable::now()->utc()),
        ]);
    }
}
