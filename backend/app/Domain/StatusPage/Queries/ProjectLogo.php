<?php

namespace App\Domain\StatusPage\Queries;

use App\Domain\Projects\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A project's logo (D-048): a file on the private disk. Its URL keeps monitoreo's API paths, with
 * `?v=<updated_at>` so it can be cached forever.
 */
final class ProjectLogo
{
    public static function path(Project $project): string
    {
        return "status-page/logos/{$project->id}";
    }

    /** The client's URL goes by slug; the team's by id. */
    public static function url(Project $project, bool $forClient): ?string
    {
        if ($project->logo_path === null || $project->logo_updated_at === null) {
            return null;
        }
        $version = $project->logo_updated_at->getTimestamp();

        return $forClient
            ? "api/portal/projects/{$project->slug}/logo?v={$version}"
            : "api/portal-admin/projects/{$project->id}/logo?v={$version}";
    }

    public static function response(Project $project): Response
    {
        $disk = Storage::disk('local');
        if ($project->logo_path === null || ! $disk->exists($project->logo_path)) {
            throw new NotFoundHttpException('El proyecto no tiene logo.');
        }

        return response((string) $disk->get($project->logo_path), 200, [
            'Content-Type' => $project->logo_type,
            'Cache-Control' => 'private, max-age=31536000, immutable', // the URL carries ?v=<updated_at>
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
