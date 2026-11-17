<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\StatusPage\Queries\ProjectAdmin;
use App\Domain\StatusPage\Queries\ProjectLookup;
use App\Domain\Support\Queries\ReportSearch;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('projects_list')]
#[Description('Lista los proyectos de los clientes con su cliente, desarrolladores, estado actual, cuántos reportes abiertos y con mensajes sin leer tiene cada uno y cuántas vulnerabilidades de dependencias. Por defecto solo los tuyos.')]
#[IsReadOnly]
final class ProjectsList extends MonitoreoTool
{
    protected function properties(): array
    {
        return ['query' => ['type' => 'string', 'description' => 'Filtra por nombre de proyecto o de cliente.'], 'all' => self::ALL];
    }

    public function answer(Request $request, ProjectLookup $lookup, ProjectAdmin $admin, ReportSearch $reports): Response
    {
        $all = (bool) $request->get('all');
        $counts = $reports->countsByProject();
        $query = mb_strtolower(trim((string) $request->get('query')));
        $out = [];
        foreach ($admin->rows($lookup->scoped(self::user(), $all)) as $row) {
            if ($query !== '' && ! str_contains(mb_strtolower("{$row['name']} {$row['client_name']} {$row['slug']}"), $query)) {
                continue;
            }
            $out[] = ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug'], 'client' => $row['client_name'],
                'visible_to_client' => $row['active'], 'status' => $row['status'], 'open_reports' => $counts[$row['id']]['open'] ?? 0,
                'unread_reports' => $counts[$row['id']]['unread'] ?? 0, 'developers' => $row['developers'], 'vulnerabilities' => $row['vulns']];
        }

        return self::json(['projects' => $out, 'total' => count($out), 'scope' => $all ? 'all' : 'mine']);
    }
}
