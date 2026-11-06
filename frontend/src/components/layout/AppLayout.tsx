import { NavLink, Outlet } from 'react-router'
import { useCanAny, useCurrentUser } from '@/features/auth/useAuth'
import { CLIENTS_READ_PERMISSIONS } from '@/features/clients/types'
import { SETTINGS_PERMISSIONS } from '@/features/settings/settingsTabs'
import { UserMenu } from './UserMenu'
import { useTheme } from './useTheme'

interface NavigationItem {
  to: string
  label: string
  visible: boolean
}

const linkClassName = ({ isActive }: { isActive: boolean }) => (isActive ? 'text-accent' : 'text-muted hover:text-text')

export function AppLayout() {
  const { data: user } = useCurrentUser()
  const { theme, toggleTheme } = useTheme()
  const canSeeSettings = useCanAny(SETTINGS_PERMISSIONS)
  const canSeeClients = useCanAny(CLIENTS_READ_PERMISSIONS)

  /** Feature modules register their top-level entries here. */
  const navigation: NavigationItem[] = [
    { to: '/', label: 'Mis tareas', visible: true },
    { to: '/proyectos', label: 'Proyectos', visible: true },
    { to: '/clientes', label: 'Clientes', visible: canSeeClients },
    { to: '/personas', label: 'Personas', visible: true },
    { to: '/configuracion', label: 'Configuración', visible: canSeeSettings },
  ]

  return (
    <div className="min-h-screen">
      <header className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-border bg-surface px-4 py-3">
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
        {/* RF-05.01: the global timer mounts here (E4). */}
        <div id="global-timer-slot" className="ml-auto" />
        <button type="button" onClick={toggleTheme} className="text-sm text-muted" aria-label="Cambiar tema">
          {theme === 'dark' ? 'Claro' : 'Oscuro'}
        </button>
        <UserMenu />
      </header>
      <main className="p-4">
        <Outlet />
      </main>
    </div>
  )
}
