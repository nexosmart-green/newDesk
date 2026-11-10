import type { ReactNode } from 'react'
import { Navigate, Outlet } from 'react-router'
import { TabNav } from '@/components/ui/TabNav'
import { useCurrentUser } from '@/features/auth/useAuth'
import { visibleSettingsTabs } from './settingsTabs'

function useVisibleTabs() {
  const { data: user } = useCurrentUser()
  return visibleSettingsTabs(user?.permissions ?? [])
}

export function SettingsPage() {
  const tabs = useVisibleTabs()

  if (tabs.length === 0) {
    return <p className="text-muted">No tenés acceso a la configuración.</p>
  }

  return (
    <section className="max-w-5xl space-y-4">
      <h1 className="text-lg font-semibold">Configuración</h1>
      <TabNav label="Secciones de configuración" tabs={tabs} />
      <Outlet />
    </section>
  )
}

/** `/configuracion` lands on the first tab the user can see. */
export function SettingsIndexRedirect() {
  const [firstTab] = useVisibleTabs()
  return firstTab ? <Navigate to={firstTab.path} replace /> : null
}

/** Direct URL to a hidden tab falls back to the index instead of showing a 403 wall. */
export function SettingsTabGuard({ path, children }: { path: string; children: ReactNode }) {
  const tabs = useVisibleTabs()
  if (!tabs.some((tab) => tab.path === path)) return <Navigate to="/configuracion" replace />
  return children
}
