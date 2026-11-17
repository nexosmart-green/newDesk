<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\SyncProjectMembers;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * The team's project form (monitoreo PUT /api/portal-admin/projects/{id}). Name and code are edited in
 * Proyectos (RF-03); this form writes the status page fields. A new domain forgets what was learned about
 * the old one, the errors token is replaced only when a new one comes (and cleared with the URL), and the
 * developers, when sent, become the leader (through UpdateProject) and the members (ADR-0002 §6).
 */
final class UpdateStatusProject
{
    public function __construct(
        private readonly UpdateProject $updateProject,
        private readonly SyncProjectMembers $syncMembers,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  array<string, mixed>  $input  validated by PortalProjectRequest */
    public function __invoke(Project $project, array $input, User $actor): Project
    {
        return DB::transaction(function () use ($project, $input, $actor) {
            $developers = $input['developers'] ?? null;
            if ($developers !== null) {
                $project = ($this->updateProject)($actor, $project, ['leader_user_id' => $developers['lead_user_id'] ?? null]);
            }

            $before = $project->only(Project::STATUS_PAGE);
            $project->fill(array_intersect_key($input, array_flip(Project::STATUS_PAGE)));
            if ($project->isDirty('domain')) {
                $project->forceFill(['domain_expires_at' => null, 'domain_checked_at' => null, 'domain_notice_level' => 0]);
            }
            if ($project->errors_url === null) {
                $project->errors_token = null;
            } elseif (filled($input['errors_token'] ?? null)) {
                $project->errors_token = $input['errors_token'];
            }
            $tokenChanged = $project->isDirty('errors_token');
            $project->save();

            ($this->recordActivity)('project.status_page_updated', $project, $actor, $before, $project->only(Project::STATUS_PAGE), $project->id);
            if ($tokenChanged) {
                // Only that it changed: the token never reaches the audit trail.
                ($this->recordActivity)('project.errors_token_changed', $project, $actor, null, ['has_errors_token' => $project->errors_token !== null], $project->id);
            }

            if ($developers !== null) {
                $secondaries = array_diff($developers['secondary_user_ids'] ?? [], [$project->leader_user_id]);
                ($this->syncMembers)($project, array_values($secondaries), $actor);
            }

            return $project;
        });
    }
}
