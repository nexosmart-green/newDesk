import '@fontsource-variable/inter/wght.css'
import './skin.css'

import { useEffect, useRef, useState, type MouseEvent, type RefObject } from 'react'
import { NavLink, Outlet } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { useServers } from './api/useMonitoring'
import { Icon, type IconName } from './components/Icon'
import { Spinner } from './components/Panel'
import { useEvents, type Connection } from './realtime/useEvents'

// Proyectos, Clientes y Reportes llegan en fases siguientes; Configuración es solo para admin (monitoring.manage).
const nav: { to: string; label: string; icon: IconName; end?: boolean; admin?: boolean }[] = [
  { to: '/monitoreo', label: 'Servidores', icon: 'servers', end: true },
  { to: '/monitoreo/alerts', label: 'Alertas', icon: 'bell' },
  { to: '/monitoreo/settings', label: 'Configuración', icon: 'cog', admin: true },
]

const connectionText: Record<Connection, string> = { open: 'En vivo', connecting: 'Conectando', retrying: 'Reconectando' }

// El layout del panel de monitoreo dentro del de newDesk: el header (usuario, tema, salir) es el de newDesk.
export function MonitoringLayout() {
  // Una sola conexión a Reverb para todo el módulo; el modo en vivo lo pide ServerPage.
  const connection = useEvents()
  const isAdmin = useCan('monitoring.manage')
  const [menuOpen, setMenuOpen] = useState(false)
  const menuButton = useRef<HTMLButtonElement>(null)
  const sidebar = useRef<HTMLElement>(null)

  // Cajón móvil: foco adentro al abrir; Escape o pasar a escritorio lo cierran y el foco vuelve al botón.
  useEffect(() => {
    if (!menuOpen) return
    sidebar.current?.querySelector<HTMLElement>('a')?.focus()
    const close = () => setMenuOpen(false)
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') close()
    }
    const desktop = window.matchMedia('(min-width: 64rem)')
    document.addEventListener('keydown', onKeyDown)
    desktop.addEventListener('change', close)
    const button = menuButton.current
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      desktop.removeEventListener('change', close)
      button?.focus()
    }
  }, [menuOpen])

  const skipToMain = (event: MouseEvent) => {
    event.preventDefault()
    document.getElementById('main')?.focus()
  }

  return (
    // -m-4: al ras del <main className="p-4"> de AppLayout, como el panel original.
    <div data-skin="monitoreo" className="-m-4 min-h-dvh lg:grid lg:grid-cols-[16rem_1fr]">
      <a href="#main" onClick={skipToMain} className="btn btn-primary sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50">
        Saltar al contenido
      </a>
      <aside id="sidebar" ref={sidebar} className="drawer glass flex flex-col border-r border-line" data-open={menuOpen || undefined}>
        <Brand />
        <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-3" aria-label="Monitoreo">
          {nav
            .filter((item) => isAdmin || !item.admin)
            .map((item) => (
              <NavItem key={item.to} {...item} onNavigate={() => setMenuOpen(false)} />
            ))}
        </nav>
        {/* sticky: con el header de newDesk arriba, la sidebar arranca más abajo y el estado quedaría fuera de la pantalla. */}
        <div className="space-y-3 border-t border-line p-4 lg:sticky lg:bottom-0">
          <div role="status" className="flex items-center gap-2 text-xs text-muted">
            <ConnectionDot connection={connection} />
            {connectionText[connection]}
          </div>
        </div>
      </aside>
      <button type="button" aria-label="Cerrar menú" tabIndex={-1} className="scrim" data-open={menuOpen || undefined} onClick={() => setMenuOpen(false)} />

      <div className="min-w-0" inert={menuOpen}>
        <MobileBar open={menuOpen} onMenu={() => setMenuOpen(true)} buttonRef={menuButton} connection={connection} />
        {/* div y no <main>: el <main> de la página es el de AppLayout. */}
        <div id="main" tabIndex={-1} className="mx-auto w-full max-w-[1400px] px-5 pt-6 pb-16 outline-none sm:px-8 lg:px-10 lg:pt-10">
          <Outlet />
        </div>
      </div>
    </div>
  )
}

function BrandMark({ className = '', iconSize = 18 }: { className?: string; iconSize?: number }) {
  return (
    <span className={`grid shrink-0 place-items-center bg-accent text-accent-ink ${className}`} aria-hidden="true">
      <Icon name="bars" size={iconSize} className="stroke-[2.25]" />
    </span>
  )
}

function Brand() {
  return (
    <div className="flex items-center gap-2.5 px-5 pt-5 pb-4">
      <BrandMark className="size-8 rounded-[9px]" />
      <div className="leading-tight">
        <div className="text-headline font-semibold">Monitoreo</div>
        <div className="text-xs text-muted">nexosmart</div>
      </div>
    </div>
  )
}

function NavItem({ to, label, icon, end, onNavigate }: (typeof nav)[number] & { onNavigate: () => void }) {
  const servers = useServers()
  // Alertas: las activas de todos los servers.
  const firing = to === '/monitoreo/alerts' ? (servers.data ?? []).reduce((sum, server) => sum + server.active_alerts, 0) : 0
  return (
    <NavLink
      to={to}
      end={end}
      onClick={onNavigate}
      className={({ isActive }) =>
        `flex h-8 items-center gap-2.5 rounded-lg px-2.5 text-sm font-medium transition-colors ${
          isActive ? 'bg-accent-soft text-text' : 'text-muted hover:bg-surface-2 hover:text-text'
        }`
      }
    >
      <Icon name={icon} />
      <span className="flex-1">{label}</span>
      {firing > 0 && (
        <span key={firing} aria-label={`${firing} activas`} className="num fade-in grid h-5 min-w-5 place-items-center rounded-full bg-crit px-1.5 text-[0.6875rem] font-semibold text-white">
          {firing}
        </span>
      )}
    </NavLink>
  )
}

function ConnectionDot({ connection }: { connection: Connection }) {
  if (connection === 'connecting') return <Spinner className="text-warn" />
  if (connection === 'retrying') return <Spinner className="text-crit" />
  return <span className="live-dot size-2 shrink-0 rounded-full bg-ok text-ok" />
}

interface MobileBarProps {
  open: boolean
  onMenu: () => void
  buttonRef: RefObject<HTMLButtonElement | null>
  connection: Connection
}

// Solo celular. El punto de conexión va sin texto: "En vivo" ya está en el sidebar.
function MobileBar({ open, onMenu, buttonRef, connection }: MobileBarProps) {
  return (
    <header className="glass sticky top-0 z-20 flex h-13 items-center gap-2 border-b border-line px-3 lg:hidden">
      <button
        ref={buttonRef}
        type="button"
        className="btn btn-ghost size-9 px-0"
        aria-label="Abrir menú"
        aria-expanded={open}
        aria-controls="sidebar"
        onClick={onMenu}
      >
        <Icon name="menu" size={18} />
      </button>
      <BrandMark className="size-7 rounded-lg" />
      <span className="text-headline font-semibold">Monitoreo</span>
      <span className="flex-1" />
      <span className="grid place-items-center px-2" title={connectionText[connection]} aria-hidden="true">
        <ConnectionDot connection={connection} />
      </span>
    </header>
  )
}
