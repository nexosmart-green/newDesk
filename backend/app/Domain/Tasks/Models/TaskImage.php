<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image pasted into a task description. Lives on the private disk and is served only to
 * people who can see its project (the markdown references `/api/v1/task-images/{uuid}`).
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property int $project_id
 * @property int $uploaded_by
 * @property string $path
 * @property string $mime
 * @property int $size
 */
class TaskImage extends Model
{
    use BelongsToCompany;

    public const DISK = 'local';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function url(): string
    {
        return "/api/v1/task-images/{$this->uuid}";
    }
}
