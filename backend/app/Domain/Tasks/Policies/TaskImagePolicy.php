<?php

namespace App\Domain\Tasks\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskImage;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * An image is as visible as its project (404 for outsiders, CA-03.03).
 */
class TaskImagePolicy
{
    public function view(User $user, TaskImage $image): Response
    {
        $project = $image->project;

        return $project !== null && Gate::forUser($user)->allows('view', $project) ? Response::allow() : Response::denyAsNotFound();
    }
}
