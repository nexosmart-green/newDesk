<?php

namespace App\Domain\Identity\Enums;

/**
 * Granular permission catalog (RF-01.03, P-02). Lives in code, not in a seeder,
 * so every environment has exactly the same catalog. Roles are editable sets of these.
 */
enum Permission: string
{
    case ClientsManage = 'clients.manage';
    case ProjectsManage = 'projects.manage';
    case MembersManage = 'members.manage';
    case HoursAllocate = 'hours.allocate';
    case TasksAssign = 'tasks.assign';
    case TimeTrack = 'time.track';
    /** Edit/delete other people's entries and load manual hours beyond the allowed window (RF-05.09/15). */
    case TimeManage = 'time.manage';
    case RatesView = 'rates.view';
    case ShiftsManage = 'shifts.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case CompanyManage = 'company.manage';
    case McpUse = 'mcp.use';
    /** See/revoke anyone's MCP tokens (RF-12.05). */
    case McpManageTokens = 'mcp.manage_tokens';
    /** Servers, alerts, project status and client reports (RF-13). */
    case MonitoringView = 'monitoring.view';
    /** What monitoreo's admin did: servers, components, client portal users (RF-13). */
    case MonitoringManage = 'monitoring.manage';

    public function label(): string
    {
        return match ($this) {
            self::ClientsManage => 'Gestionar clientes',
            self::ProjectsManage => 'Gestionar proyectos',
            self::MembersManage => 'Asignar miembros a proyectos',
            self::HoursAllocate => 'Cargar / ampliar horas',
            self::TasksAssign => 'Asignar tareas',
            self::TimeTrack => 'Cronometrar',
            self::TimeManage => 'Gestionar horas de otros',
            self::RatesView => 'Ver tarifas y rentabilidad',
            self::ShiftsManage => 'Armar turnos',
            self::UsersManage => 'Gestionar usuarios',
            self::RolesManage => 'Gestionar roles y permisos',
            self::CompanyManage => 'Configurar la empresa',
            self::McpUse => 'Usar MCP (Claude Code)',
            self::McpManageTokens => 'Ver y revocar tokens MCP de todos',
            self::MonitoringView => 'Ver monitoreo',
            self::MonitoringManage => 'Administrar monitoreo',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }
}
