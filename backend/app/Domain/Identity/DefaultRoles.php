<?php

namespace App\Domain\Identity;

use App\Domain\Identity\Enums\Permission;

/**
 * Base roles every company starts with (RF-01.03). The owner edits them afterwards.
 */
final class DefaultRoles
{
    public const ADMIN = 'Admin';

    public const MANAGER = 'Gestor';

    public const COLLABORATOR = 'Colaborador';

    /** @return array<string, list<Permission>> */
    public static function definitions(): array
    {
        return [
            self::ADMIN => Permission::cases(),
            self::MANAGER => [
                Permission::ProjectsManage,
                Permission::MembersManage,
                Permission::HoursAllocate,
                Permission::TasksAssign,
                Permission::TimeTrack,
                Permission::McpUse,
            ],
            self::COLLABORATOR => [
                Permission::TasksAssign,
                Permission::TimeTrack,
                Permission::McpUse,
            ],
        ];
    }
}
