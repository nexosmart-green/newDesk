<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Task comment (RF-04.06). Company scope comes through its task.
 *
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property string $body
 */
class TaskComment extends Model
{
    use SoftDeletes;

    protected $fillable = ['task_id', 'user_id', 'body'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_mentions', 'comment_id')->withPivot('read_at');
    }
}
