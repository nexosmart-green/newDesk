<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Tasks\Enums\StatusKind;
use Illuminate\Database\Eloquent\Model;

/**
 * Company-level task status (not per project), with order, color and kind (RF-04.03).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $color
 * @property StatusKind $kind
 * @property int $position
 */
class TaskStatus extends Model
{
    use BelongsToCompany;

    /** Base statuses for a new company. */
    public const DEFAULTS = [
        ['name' => 'Pendiente', 'color' => '#9AA3B2', 'kind' => 'open'],
        ['name' => 'En curso', 'color' => '#4F8CFF', 'kind' => 'open'],
        ['name' => 'Bloqueado', 'color' => '#FF6B6B', 'kind' => 'blocked'],
        ['name' => 'En revisión', 'color' => '#F5B942', 'kind' => 'open'],
        ['name' => 'Completada', 'color' => '#3ECF8E', 'kind' => 'closed'],
    ];

    protected $fillable = ['company_id', 'name', 'color', 'kind', 'position'];

    protected function casts(): array
    {
        return ['kind' => StatusKind::class, 'position' => 'integer'];
    }

    public function isClosed(): bool
    {
        return $this->kind === StatusKind::Closed;
    }

    /** The status new tasks start in: the first open one by position. */
    public static function initialFor(int $companyId): self
    {
        return self::query()->forCompany($companyId)->where('kind', StatusKind::Open)->orderBy('position')->firstOrFail();
    }
}
