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

    /** monitoreo's team (P-29). */
    public const DEVELOPER = 'Desarrollador';

    /** Client portal users (RF-13.07): no permission, routes gate them by `client_id`. Not a system role: that one is Admin. */
    public const CLIENT = 'Cliente';

    /** @return array<string, list<Permission>> */
    public static function definitions(): array
    {
        return [
            self::ADMIN => Permission::cases(),
            self::MANAGER => [
                ...Permission::modules(),
                Permission::ProjectsManage,
                Permission::MembersManage,
                Permission::HoursAllocate,
                Permission::TasksAssign,
                Permission::TimeTrack,
                Permission::McpUse,
            ],
            self::COLLABORATOR => [
                Permission::ModuleProjects,
                Permission::ModuleTime,
                Permission::ModulePeople,
                Permission::ModuleCalendar,
                Permission::TasksAssign,
                Permission::TimeTrack,
                Permission::McpUse,
            ],
            self::DEVELOPER => [
                Permission::ModuleProjects,
                Permission::ModuleTime,
                Permission::ModulePeople,
                Permission::ModuleCalendar,
                Permission::TasksAssign,
                Permission::TimeTrack,
                Permission::McpUse,
                Permission::MonitoringView,
            ],
            self::CLIENT => [],
        ];
    }
}
