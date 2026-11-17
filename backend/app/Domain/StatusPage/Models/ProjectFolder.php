<?php

namespace App\Domain\StatusPage\Models;

use App\Domain\Monitoring\Models\Server;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inventory folder (a project path on a server) linked to a client project: one project at most
 * (D-044). It gives the "last update" and the suggested components.
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property int $server_id
 * @property string $path
 */
class ProjectFolder extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $table = 'status_project_folders';

    protected $guarded = [];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
