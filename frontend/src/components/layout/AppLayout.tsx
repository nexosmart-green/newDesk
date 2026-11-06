import { NavLink, Outlet } from 'react-router'
import { useCurrentUser, useLogout } from '@/features/auth/useAuth'
import { useTheme } from './useTheme'

/** Feature modules register their top-level entries here. */
const navigation = [{ to: '/', label: 'Mis tareas' }]

export function AppLayout() {
  const { data: user } = useCurrentUser()
  const logoutMutation = useLogout()
  const { theme, toggleTheme } = useTheme()

  return (
    <div className="min-h-screen">
      <header className="flex flex-wrap items-center gap-4 border-b border-border bg-surface px-4 py-3">
        <span className="font-semibold">{user?.company.name}</span>
        <nav className="flex gap-3 text-sm">
          {navigation.map((item) => (
            <NavLink key={item.to} to={item.to} className={({ isActive }) => (isActive ? 'text-accent' : 'text-muted')}>
              {item.label}
            </NavLink>
          ))}
        </nav>
        {/* RF-05.01: the global timer mounts here (E4). */}
        <div id="global-timer-slot" className="ml-auto" />
        <button type="button" onClick={toggleTheme} className="text-sm text-muted" aria-label="Cambiar tema">
          {theme === 'dark' ? 'Claro' : 'Oscuro'}
        </button>
        <span className="text-sm">{user?.full_name}</span>
        <button type="button" onClick={() => logoutMutation.mutate()} className="text-sm text-muted">
          Salir
        </button>
      </header>
      <main className="p-4">
        <Outlet />
      </main>
    </div>
  )
}
