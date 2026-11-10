<?php

namespace App\Domain\Identity\Enums;

/**
 * Granular permission catalog (RF-01.03, P-02). Lives in code, not in a seeder,
 * so every environment has exactly the same catalog. Roles are editable sets of these.
 */
enum Permission: string
{
    // Module access: the Admin turns whole areas of the system on/off per role (pedido 2026-11-10).
    case ModuleProjects = 'module.projects';
    case ModuleTime = 'module.time';
    case ModuleClients = 'module.clients';
    case ModulePeople = 'module.people';
    case ModuleCalendar = 'module.calendar';
    case ModuleReports = 'module.reports';

    case ClientsManage = 'clients.manage';
    case ProjectsManage = 'projects.manage';
    /** RF-03.08: reopening a finished/cancelled project is an Admin decision. */
    case ProjectsReopen = 'projects.reopen';
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

    public function label(): string
    {
        return match ($this) {
            self::ModuleProjects => 'Módulo Proyectos y tareas',
            self::ModuleTime => 'Módulo Horas y cronómetro',
            self::ModuleClients => 'Módulo Clientes',
            self::ModulePeople => 'Módulo Personas',
            self::ModuleCalendar => 'Módulo Turnos y calendario',
            self::ModuleReports => 'Módulo Reportes',
            self::ClientsManage => 'Gestionar clientes',
            self::ProjectsManage => 'Gestionar proyectos',
            self::ProjectsReopen => 'Reabrir proyectos finalizados o cancelados',
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
        };
    }

    public function isModule(): bool
    {
        return str_starts_with($this->value, 'module.');
    }

    /** @return list<self> */
    public static function modules(): array
    {
        return array_values(array_filter(self::cases(), fn (self $permission) => $permission->isModule()));
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }
}
