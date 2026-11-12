<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Queries\TimerState;
use App\Mcp\Support\McpPresenter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_active_timer')]
#[Description('Mi cronómetro activo y el tiempo corrido (calculado en el servidor).')]
#[IsReadOnly]
class GetActiveTimerTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly TimerState $timerState) {}

    protected function run(Request $request, User $user): Response
    {
        $active = $this->timerState->active($user);

        return $this->json(['active_timer' => $active ? McpPresenter::entry($active) : null]);
    }
}
