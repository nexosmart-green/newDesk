<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Mcp\Support\McpPresenter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('Proyectos donde soy miembro, con horas cargadas, consumidas y disponibles.')]
#[IsReadOnly]
class ListProjects extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    protected function run(Request $request, User $user): Response
    {
        $projects = Project::query()->forCompany($user->company_id)
            ->visibleTo($user, false) // RF-12: "proyectos donde soy miembro", even for managers
            ->orderBy('name')->get();

        return $this->json($projects->map(fn (Project $project) => McpPresenter::project($project))->all());
    }
}
