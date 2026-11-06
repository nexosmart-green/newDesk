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

#[Name('get_active_timer')]
#[Description('Mi cronómetro activo y el tiempo corrido (calculado en el servidor).')]
#[IsReadOnly]
class GetActiveTimer extends NewDeskTool
{
    protected function run(Request $request, User $user): Response
    {
        $active = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->first();

        return $this->json(['active_timer' => $active ? McpPresenter::entry($active) : null]);
    }
}
