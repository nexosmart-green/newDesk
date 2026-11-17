<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\StatusPage\Models\ProjectFolder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the inventory folders linked to a project (D-044). Only folders the agent lists (a typo
 * would never match anything), and each belongs to one client project at most. Every rejection is
 * listed under `folders`.
 */
final class SetProjectFolders
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  list<array{server_id: int, path: string}>  $folders */
    public function __invoke(Project $project, array $folders, User $actor): void
    {
        $inventories = ServerSnapshot::query()->where('section', 'projects')->pluck('data', 'server_id')
            ->map(fn (?string $data) => array_column(json_decode((string) $data, true)['items'] ?? [], 'path'));
        $owners = ProjectFolder::query()->with(['project:id,name,client_id', 'project.client:id,legal_name,trade_name'])->get()
            ->keyBy(fn (ProjectFolder $folder) => "{$folder->server_id}|{$folder->path}");

        [$problems, $chosen] = [[], []];
        foreach ($folders as $folder) {
            $key = "{$folder['server_id']}|{$folder['path']}";
            if (isset($chosen[$key])) {
                continue;
            }
            $owner = $owners->get($key);
            if (! in_array($folder['path'], $inventories->get($folder['server_id'], []), true)) {
                $problems[] = "{$folder['path']} no está en el inventario de ese servidor.";
            } elseif ($owner !== null && $owner->project_id !== $project->id) {
                $problems[] = "{$folder['path']} ya es de {$owner->project->client->displayName()} ({$owner->project->name}).";
            } else {
                $chosen[$key] = ['server_id' => $folder['server_id'], 'path' => $folder['path']];
            }
        }
        if ($problems !== []) {
            throw ValidationException::withMessages(['folders' => $problems]);
        }

        try {
            DB::transaction(function () use ($project, $chosen, $actor) {
                $before = ProjectFolder::query()->where('project_id', $project->id)->orderBy('id')->get(['server_id', 'path'])->toArray();
                ProjectFolder::query()->where('project_id', $project->id)->delete();
                foreach ($chosen as $folder) {
                    ProjectFolder::query()->create(['company_id' => $project->company_id, 'project_id' => $project->id, ...$folder]);
                }
                ($this->recordActivity)('project.folders_set', $project, $actor, ['folders' => $before], ['folders' => array_values($chosen)], $project->id);
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('Otra persona vinculó una de esas carpetas recién. Recargá y probá de nuevo.', 'folder_taken', 409);
        }
    }
}
