<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Support\Queries\Roadmap;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/** Always the token owner's roadmap: there is no argument to ask for someone else's (D-066, CA-13.19). */
#[Name('roadmap_list')]
#[Description('Tu roadmap (es personal): los reportes que vas a resolver, en el orden en que los ordenaste en el panel (el primero es el siguiente). Cada uno con id, cliente, proyecto, urgencia, estado y descripción; el detalle sale con reports_get.')]
#[IsReadOnly]
final class RoadmapList extends MonitoreoTool
{
    protected function properties(): array
    {
        return [];
    }

    public function answer(Request $request, Roadmap $roadmap): Response
    {
        $user = self::user();
        $items = [];
        foreach ($roadmap($user) as $i => $row) {
            $items[] = ['position' => $i + 1, 'id' => $row['id'], 'client' => $row['client_name'], 'project' => $row['project_name'],
                'urgency' => $row['urgency'], 'status' => $row['status'], 'where' => $row['component_label'],
                'description' => self::untrusted($row['description']), 'unread' => $row['unread']];
        }

        return self::json(['owner' => $user->email, 'reports' => $items]);
    }
}
