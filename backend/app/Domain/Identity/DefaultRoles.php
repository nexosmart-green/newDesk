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

    /** monitoreo's team (P-26). */
    public const DEVELOPER = 'Desarrollador';

    /** Client portal users (RF-13.07): no permission, routes gate them by `client_id`. */
    public const CLIENT = 'Cliente';

    /** Roles that cannot be deleted. */
    public const SYSTEM = [self::ADMIN, self::CLIENT];

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
            self::DEVELOPER => [
                Permission::TasksAssign,
                Permission::TimeTrack,
                Permission::McpUse,
                Permission::MonitoringView,
            ],
            self::CLIENT => [],
        ];
    }
}
