import type { RefObject } from 'react'
import { NavLink } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { useServers } from './api/useMonitoring'
import { useReportsAttention, useRoadmapPending } from './api/useReports'
import { Icon, type IconName } from './components/Icon'
import { Spinner } from './components/Panel'
import type { Connection } from './realtime/useEvents'

// Las piezas del layout de monitoreo: marca, menú, estado de la conexión y la barra del celular.

// Configuración es solo para admin (monitoring.manage). Clientes se abre desde Proyectos, como en monitoreo.
const nav: { to: string; label: string; icon: IconName; end?: boolean; admin?: boolean }[] = [
  { to: '/monitoreo', label: 'Servidores', icon: 'servers', end: true },
  { to: '/monitoreo/alerts', label: 'Alertas', icon: 'bell' },
  { to: '/monitoreo/proyectos', label: 'Proyectos', icon: 'users' },
  { to: '/monitoreo/reportes', label: 'Reportes', icon: 'inbox' },
  { to: '/monitoreo/settings', label: 'Configuración', icon: 'cog', admin: true },
]

const connectionText: Record<Connection, string> = { open: 'En vivo', connecting: 'Conectando', retrying: 'Reconectando' }

function BrandMark({ className = '', iconSize = 18 }: { className?: string; iconSize?: number }) {
  return (
    <span className={`grid shrink-0 place-items-center bg-accent text-accent-ink ${className}`} aria-hidden="true">
      <Icon name="bars" size={iconSize} className="stroke-[2.25]" />
    </span>
  )
}

export function Brand() {
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
  const attention = useReportsAttention()
  // Alertas: activas. Reportes: los nuevos y los que tienen mensajes del cliente sin leer.
  const firing =
    to === '/monitoreo/alerts'
      ? (servers.data ?? []).reduce((sum, server) => sum + server.active_alerts, 0)
      : to === '/monitoreo/reportes'
        ? (attention.data ?? 0)
        : 0
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
        <span
          key={firing}
          aria-label={to === '/monitoreo/reportes' ? `${firing} para mirar` : `${firing} activas`}
          className="num fade-in grid h-5 min-w-5 place-items-center rounded-full bg-crit px-1.5 text-[0.6875rem] font-semibold text-white"
        >
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

export function SidebarNav({ onNavigate }: { onNavigate: () => void }) {
  const isAdmin = useCan('monitoring.manage')
  return (
    <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-3" aria-label="Monitoreo">
      {nav
        .filter((item) => isAdmin || !item.admin)
        .map((item) => (
          <NavItem key={item.to} {...item} onNavigate={onNavigate} />
        ))}
    </nav>
  )
}

export function ConnectionStatus({ connection }: { connection: Connection }) {
  return (
    <div role="status" className="flex items-center gap-2 text-xs text-muted">
      <ConnectionDot connection={connection} />
      {connectionText[connection]}
    </div>
  )
}

export function RoadmapToggle({ onClick, className = '' }: { onClick: () => void; className?: string }) {
  const pending = useRoadmapPending().data ?? 0
  return (
    <button type="button" className={`btn btn-sm shadow-[var(--shadow)] ${className}`} onClick={onClick} aria-label={`Abrir el roadmap (${pending} pendientes)`}>
      <Icon name="roadmap" size={14} />
      <span className="max-sm:sr-only">Roadmap</span>
      {pending > 0 && <span className="num grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1.5 text-[0.6875rem] font-semibold text-accent-ink">{pending}</span>}
    </button>
  )
}

interface MobileBarProps {
  open: boolean
  onMenu: () => void
  onRoadmap: () => void
  buttonRef: RefObject<HTMLButtonElement | null>
  connection: Connection
}

// Solo celular. El punto de conexión va sin texto: "En vivo" ya está en el sidebar.
export function MobileBar({ open, onMenu, onRoadmap, buttonRef, connection }: MobileBarProps) {
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
      <RoadmapToggle onClick={onRoadmap} />
    </header>
  )
}
