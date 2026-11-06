<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Mcp\Support\McpPresenter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Description('Quién soy en newDesk: usuario, rol, permisos actuales, cronómetro activo y registros con comentario pendiente (que bloquean iniciar otro cronómetro).')]
#[IsReadOnly]
class WhoAmI extends NewDeskTool
{
    protected function run(Request $request, User $user): Response
    {
        $active = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->first();
        $pending = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->where('comment_pending', true)->get();

        return $this->json([
            'user' => ['name' => $user->fullName(), 'email' => $user->email, 'timezone' => $user->effectiveTimezone()],
            'company' => $user->company->name,
            'role' => $user->role->name,
            'permissions' => $user->role->permissionValues(),
            'active_timer' => $active ? McpPresenter::entry($active) : null,
            'pending_comments' => $pending->map(fn (TimeEntry $entry) => McpPresenter::entry($entry))->values()->all(),
            'notice' => $pending->isNotEmpty() ? 'Completá los comentarios pendientes con complete_pending_comment antes de iniciar otro cronómetro.' : null,
        ]);
    }
}
