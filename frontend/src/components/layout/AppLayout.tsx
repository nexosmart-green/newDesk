import { Suspense } from 'react'
import { NavLink, Outlet } from 'react-router'
import { useCan, useCanAny, useCurrentUser } from '@/features/auth/useAuth'
import { CLIENTS_READ_PERMISSIONS } from '@/features/clients/types'
import { REPORTS_PERMISSION } from '@/features/reports/types'
import { SHIFTS_PERMISSION } from '@/features/shifts/types'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { SETTINGS_PERMISSIONS } from '@/features/settings/settingsTabs'
import { GlobalTimer } from '@/features/timer/GlobalTimer'
import { PendingCommentsBanner } from '@/features/timer/PendingCommentsBanner'
import { UserMenu } from './UserMenu'
import { useTheme } from './useTheme'

interface NavigationItem {
  to: string
  label: string
  visible: boolean
}

const linkClassName = ({ isActive }: { isActive: boolean }) =>
  `whitespace-nowrap ${isActive ? 'text-accent' : 'text-muted hover:text-text'}`

export function AppLayout() {
  const { data: user } = useCurrentUser()
  const { theme, toggleTheme } = useTheme()
  const canSeeSettings = useCanAny(SETTINGS_PERMISSIONS)
  const canSeeClients = useCanAny(CLIENTS_READ_PERMISSIONS)
  const canManageShifts = useCan(SHIFTS_PERMISSION)
  const canSeeReports = useCan(REPORTS_PERMISSION)

  /** Feature modules register their top-level entries here. */
  const navigation: NavigationItem[] = [
    { to: '/', label: 'Mis tareas', visible: true },
    { to: '/proyectos', label: 'Proyectos', visible: true },
    { to: '/mi-semana', label: 'Mi semana', visible: true },
    { to: '/calendario', label: 'Calendario', visible: true },
    { to: '/turnos', label: 'Turnos', visible: canManageShifts },
    { to: '/reportes/horas', label: 'Reportes', visible: canSeeReports },
    { to: '/clientes', label: 'Clientes', visible: canSeeClients },
    { to: '/personas', label: 'Personas', visible: true },
    { to: '/configuracion', label: 'Configuración', visible: canSeeSettings },
  ]

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-40 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-border bg-surface px-4 py-3">
        <span className="font-semibold">{user?.company.name}</span>
        <nav aria-label="Principal" className="order-last flex w-full gap-3 overflow-x-auto text-sm sm:order-none sm:w-auto">
          {navigation
            .filter((item) => item.visible)
            .map((item) => (
              <NavLink key={item.to} to={item.to} end={item.to === '/'} className={linkClassName}>
                {item.label}
              </NavLink>
            ))}
        </nav>
        <div className="ml-auto flex items-center gap-3">
          <GlobalTimer />
          <NotificationBell />
          <button type="button" onClick={toggleTheme} className="text-sm text-muted" aria-label="Cambiar tema">
            {theme === 'dark' ? 'Claro' : 'Oscuro'}
          </button>
          <UserMenu />
        </div>
      </header>
      <PendingCommentsBanner />
      <main className="p-4">
        <Suspense fallback={<p className="text-muted">Cargando…</p>}>
          <Outlet />
        </Suspense>
      </main>
    </div>
  )
}
