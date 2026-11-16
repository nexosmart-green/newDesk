<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Company catalog of colored labels; a task carries any number of them.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $color #rrggbb
 */
class TaskLabel extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'color'];

    /** @return BelongsToMany<Task, $this> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label', 'label_id', 'task_id');
    }
}
