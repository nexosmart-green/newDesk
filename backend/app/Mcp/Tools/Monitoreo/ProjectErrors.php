<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\StatusPage\Queries\ProjectErrors as ErrorsEndpoint;
use App\Domain\StatusPage\Queries\ProjectLookup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('project_errors')]
#[Description('Los últimos errores de hoy de la app del proyecto (su /api/errors): nivel, mensaje, método, URL y excepción. Solo si el proyecto tiene la URL y el token de errores cargados.')]
#[IsReadOnly]
final class ProjectErrors extends MonitoreoTool
{
    protected function properties(): array
    {
        return ['project' => self::PROJECT];
    }

    protected function required(): array
    {
        return ['project'];
    }

    public function answer(Request $request, ProjectLookup $lookup, ErrorsEndpoint $errors): Response
    {
        $project = $lookup->find((string) $request->get('project'));
        $recent = $errors->recent($project);
        if ($recent === null) {
            return Response::text("{$project->name} no tiene cargados la URL y el token de errores (/api/errors); se cargan en la ficha del proyecto.");
        }
        if ($recent['error'] !== null) {
            return Response::error("No pude leer los errores de {$project->name}: {$recent['error']}.");
        }

        return self::json(['project' => $project->name, 'errors_today' => self::untrustedErrors($recent['items']), 'note' => 'Los 10 más nuevos de hoy.']);
    }
}
