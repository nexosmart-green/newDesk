export type UserStatus = 'invited' | 'active' | 'inactive'

export const userStatusLabels: Record<UserStatus, string> = {
  invited: 'Invitado',
  active: 'Activo',
  inactive: 'Inactivo',
}

export interface UserSummary {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  status: UserStatus
  role: { id: number; name: string }
  last_login_at: string | null
}

export interface UserFilters {
  status: UserStatus | ''
  search: string
  page: number
}

export interface InviteUserInput {
  first_name: string
  last_name: string
  email: string
  role_id: number
}
