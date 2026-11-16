<?php

namespace App\Domain\Tasks\Policies;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskAttachment;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * An attachment is as visible as its task (404 for outsiders, CA-03.03). Deleting it is for whoever
 * uploaded it or `projects.manage`.
 */
class TaskAttachmentPolicy
{
    public function view(User $user, TaskAttachment $attachment): Response
    {
        $task = $attachment->task;

        return $task !== null ? Gate::forUser($user)->inspect('view', $task) : Response::denyAsNotFound();
    }

    public function delete(User $user, TaskAttachment $attachment): Response
    {
        $view = $this->view($user, $attachment);
        if ($view->denied()) {
            return $view;
        }

        return self::mayDelete($user, $attachment)
            ? Response::allow()
            : Response::deny('Solo quien subió el archivo o quien gestiona proyectos puede borrarlo.');
    }

    /** For attachments of a task the viewer already sees (lists compute `can_delete` with it, no queries). */
    public static function mayDelete(User $user, TaskAttachment $attachment): bool
    {
        return $attachment->uploaded_by === $user->id || $user->hasPermission(Permission::ProjectsManage);
    }
}
