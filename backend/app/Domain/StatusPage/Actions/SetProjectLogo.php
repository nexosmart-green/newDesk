<?php

namespace App\Domain\StatusPage\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\StatusPage\Queries\ProjectLogo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sets or removes the logo the client sees on its status page (D-048). The image is a file on the
 * private disk, already checked by the caller (PNG, JPEG or WebP whose bytes match, ≤ 256 KB).
 */
final class SetProjectLogo
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  string|null  $image  null removes it */
    public function __invoke(Project $project, ?string $image, ?string $contentType, User $actor): Project
    {
        $path = ProjectLogo::path($project);
        if ($image === null) {
            Storage::disk('local')->delete($path);
        } else {
            Storage::disk('local')->put($path, $image);
        }

        return DB::transaction(function () use ($project, $image, $contentType, $path, $actor) {
            $project->forceFill($image === null
                ? ['logo_path' => null, 'logo_type' => null, 'logo_updated_at' => null]
                : ['logo_path' => $path, 'logo_type' => $contentType, 'logo_updated_at' => CarbonImmutable::now()->utc()],
            )->save();
            ($this->recordActivity)($image === null ? 'project.logo_removed' : 'project.logo_updated', $project, $actor,
                null, $image === null ? null : ['bytes' => strlen($image), 'type' => $contentType], $project->id);

            return $project;
        });
    }
}
