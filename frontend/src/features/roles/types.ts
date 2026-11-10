export interface PermissionOption {
  value: string
  label: string
}

export interface Role {
  id: number
  name: string
  /** The built-in Admin role: read-only (backend answers `system_role_locked`). */
  is_system: boolean
  permissions: string[]
  users_count: number
}

export interface SaveRoleInput {
  name: string
  permissions: string[]
}
