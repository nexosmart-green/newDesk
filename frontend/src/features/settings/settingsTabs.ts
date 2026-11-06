/** Any of these grants access to /configuracion (UI hint; the backend authorizes each call). */
export const SETTINGS_PERMISSIONS = ['company.manage', 'roles.manage', 'users.manage'] as const

export interface SettingsTab {
  path: string
  label: string
  /** The user needs any one of them. */
  permissions: readonly string[]
}

export const settingsTabs: readonly SettingsTab[] = [
  // Everyone reads the company; only `company.manage` edits it.
  { path: 'empresa', label: 'Empresa', permissions: SETTINGS_PERMISSIONS },
  { path: 'roles', label: 'Roles', permissions: ['roles.manage', 'users.manage'] },
  { path: 'usuarios', label: 'Usuarios', permissions: ['users.manage'] },
]

export function visibleSettingsTabs(userPermissions: readonly string[]): SettingsTab[] {
  return settingsTabs.filter((tab) => tab.permissions.some((permission) => userPermissions.includes(permission)))
}
