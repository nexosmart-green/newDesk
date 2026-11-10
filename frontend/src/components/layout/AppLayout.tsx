import { Suspense, useCallback, useState } from 'react'
import { Outlet, useLocation } from 'react-router'
import { PendingCommentsBanner } from '@/features/timer/PendingCommentsBanner'
import { readStoredJson, writeStoredJson } from '@/lib/storage'
import { Header } from './Header'
import { MobileDrawer } from './MobileDrawer'
import { sectionTitle, useNavigation } from './navigation'
import { Sidebar } from './Sidebar'
import { useTheme } from './useTheme'

const COLLAPSED_KEY = 'newdesk.sidebar.collapsed'

/** App shell: dark sidebar (desk palette) + header with the live bits + content. */
export function AppLayout() {
  const { groups, footer } = useNavigation()
  const { pathname } = useLocation()
  const { theme, toggleTheme } = useTheme()
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(() => readStoredJson(COLLAPSED_KEY) === true)
  const closeDrawer = useCallback(() => setDrawerOpen(false), [])

  function toggleCollapsed() {
    setCollapsed((current) => {
      writeStoredJson(COLLAPSED_KEY, !current)
      return !current
    })
  }

  return (
    <div className="flex min-h-dvh">
      <a
        href="#contenido"
        className="sr-only z-[60] rounded-lg bg-brand-strong px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-3 focus:top-3"
      >
        Saltar al contenido
      </a>
      <Sidebar groups={groups} footer={footer} collapsed={collapsed} onToggleCollapsed={toggleCollapsed} />
      <MobileDrawer open={drawerOpen} onClose={closeDrawer} groups={groups} footer={footer} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Header
          title={sectionTitle(pathname, groups, footer)}
          theme={theme}
          onToggleTheme={toggleTheme}
          onOpenMenu={() => setDrawerOpen(true)}
        />
        <PendingCommentsBanner />
        <main id="contenido" tabIndex={-1} className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 focus:outline-none sm:px-6 lg:px-8">
          <Suspense fallback={<p className="text-muted">Cargando…</p>}>
            <Outlet />
          </Suspense>
        </main>
      </div>
    </div>
  )
}
