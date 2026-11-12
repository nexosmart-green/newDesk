/** Any of these grants access to /configuracion (UI hint; the backend authorizes each call). */
export const SETTINGS_PERMISSIONS = ['company.manage', 'roles.manage', 'users.manage', 'projects.manage', 'mcp.manage_tokens'] as const

interface SettingsTab {
  path: string
  label: string
  /** The user needs any one of them. */
  permissions: readonly string[]
}

export const settingsTabs: readonly SettingsTab[] = [
  // Everyone reads the company; only `company.manage` edits it.
  { path: 'empresa', label: 'Empresa', permissions: ['company.manage', 'roles.manage', 'users.manage'] },
  { path: 'roles', label: 'Roles', permissions: ['roles.manage', 'users.manage'] },
  { path: 'usuarios', label: 'Usuarios', permissions: ['users.manage'] },
  // People catalogs (`users.manage`) and task categories/labels (`projects.manage`).
  { path: 'catalogos', label: 'Catálogos', permissions: ['users.manage', 'projects.manage'] },
  { path: 'feriados', label: 'Feriados', permissions: ['company.manage'] },
  { path: 'tokens-ia', label: 'Tokens de IA', permissions: ['mcp.manage_tokens'] },
]

export function visibleSettingsTabs(userPermissions: readonly string[]): SettingsTab[] {
  return settingsTabs.filter((tab) => tab.permissions.some((permission) => userPermissions.includes(permission)))
}
