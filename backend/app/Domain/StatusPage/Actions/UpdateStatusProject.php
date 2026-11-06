<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Actions\SyncProjectMembers;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * The team's project form (monitoreo PUT /api/portal-admin/projects/{id}). The RF-03 fields go
 * through SaveProject; on top of that a new domain forgets what was learned about the old one, the
 * errors token is replaced only when a new one comes (and cleared with the URL), and the developers,
 * when sent, become the leader and the members (ADR-0002 §6).
 */
final class UpdateStatusProject
{
    public function __construct(
        private readonly SaveProject $saveProject,
        private readonly SyncProjectMembers $syncMembers,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  array<string, mixed>  $input  validated by PortalProjectRequest */
    public function __invoke(Project $project, array $input, User $actor): Project
    {
        return DB::transaction(function () use ($project, $input, $actor) {
            $previousDomain = $project->domain;
            $attributes = array_intersect_key($input, array_flip([
                'name', 'slug', 'code_prefix', 'primary_color', 'domain', 'domain_expires_manual', 'portal_active', 'errors_url', 'discord_channel_id',
            ]));
            $developers = $input['developers'] ?? null;
            if ($developers !== null) {
                $attributes['leader_user_id'] = $developers['lead_user_id'] ?? null;
            }
            $project = ($this->saveProject)($attributes, $actor, $project);

            $extra = [];
            if ($project->domain !== $previousDomain) {
                $extra += ['domain_expires_at' => null, 'domain_checked_at' => null, 'domain_notice_level' => 0];
            }
            if ($project->errors_url === null) {
                $extra['errors_token'] = null;
            } elseif (filled($input['errors_token'] ?? null)) {
                $extra['errors_token'] = $input['errors_token'];
            }
            if (array_key_exists('errors_token', $extra) && ($extra['errors_token'] !== null || $project->getRawOriginal('errors_token') !== null)) {
                // Only that it changed: the token never reaches the audit trail.
                ($this->recordActivity)('project.errors_token_changed', $project, $actor, null, ['has_errors_token' => $extra['errors_token'] !== null], $project->id);
            }
            $project->forceFill($extra)->save();

            if ($developers !== null) {
                $secondaries = array_diff($developers['secondary_user_ids'] ?? [], [$attributes['leader_user_id']]);
                ($this->syncMembers)($project, array_values($secondaries), $actor);
            }

            return $project;
        });
    }
}
