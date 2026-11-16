import { useQuery } from '@tanstack/react-query'
import { currentUserKey } from '@/lib/queryKeys'
import { fetchCurrentUser } from './authApi'
import type { CurrentUser } from './types'

/** Module switches per role (Configuración → Roles). The Admin role always has all of them. */
export const MODULES = {
  projects: 'module.projects',
  time: 'module.time',
  clients: 'module.clients',
  people: 'module.people',
  calendar: 'module.calendar',
  reports: 'module.reports',
} as const

export type ModulePermission = (typeof MODULES)[keyof typeof MODULES]

export const ALL_MODULES: ModulePermission[] = Object.values(MODULES)

export function isModulePermission(value: string): value is ModulePermission {
  return value.startsWith('module.')
}

/**
 * UI hint only (the backend answers 403 `module_disabled`). Reads the cached /me without fetching it:
 * inside the app shell RequireAuth already loaded it; while unknown, nothing is hidden.
 */
export function useHasModule(module: ModulePermission): boolean {
  const { data } = useQuery<CurrentUser | null>({ queryKey: currentUserKey, queryFn: fetchCurrentUser, enabled: false })
  return data ? data.permissions.includes(module) : true
}
