<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Company catalog: what kind of work a task is (General, Backend-API, Ticket…). One per task.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 */
class TaskCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name'];

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'category_id');
    }
}
