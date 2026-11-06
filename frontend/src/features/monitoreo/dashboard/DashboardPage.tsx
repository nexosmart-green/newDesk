import { useEffect, useId, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { errorMessage } from '../api/monitoringApi'
import { useServers } from '../api/useMonitoring'
import type { ServerStatus, ServerSummary } from '../api/types'
import { Icon } from '../components/Icon'
import { Empty, ErrorNote, LoadingStatus } from '../components/Panel'
import { serverStatusMeta, toneColor } from '../lib/status'
import { ProjectSearch } from './ProjectSearch'
import { ServerCard, ServerCardSkeleton } from './ServerCard'

// Orden: primero lo que necesita atención.
const statusOrder: Record<ServerStatus, number> = { down: 0, agent_down: 1, unknown: 2, online: 3, disabled: 4 }

function sortServers(servers: ServerSummary[]): ServerSummary[] {
  return [...servers].sort((a, b) => statusOrder[a.status] - statusOrder[b.status] || b.active_alerts - a.active_alerts || a.name.localeCompare(b.name))
}

// Reloj que re-renderiza cada 5 s para que "hace X" avance solo.
function useNow(intervalMs = 5000): Date {
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), intervalMs)
    return () => window.clearInterval(timer)
  }, [intervalMs])
  return now
}

const pill = 'flex h-8 items-center gap-2 rounded-full bg-surface px-3 text-footnote shadow-[var(--shadow)]'

export function DashboardPage() {
  const servers = useServers()
  const canManage = useCan('monitoring.manage')
  const now = useNow()
  const [params, setParams] = useSearchParams()
  const query = params.get('q') ?? ''
  const searchId = useId()

  return (
    <div className="rise space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="title">Servidores</h1>
        {servers.data && <StatusPills servers={servers.data} />}
        {servers.isPending && (
          <div className="delay-in flex gap-2" aria-hidden="true">
            <span className="skeleton h-8 w-24 rounded-full" />
            <span className="skeleton h-8 w-24 rounded-full" />
            <span className="skeleton h-8 w-32 rounded-full" />
          </div>
        )}
      </div>

      {servers.isPending && (
        <div className="delay-in grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <LoadingStatus label="Cargando servidores…" />
          {[0, 1, 2, 3, 4, 5].map((index) => (
            <ServerCardSkeleton key={index} />
          ))}
        </div>
      )}
      {servers.isError && <ErrorNote onRetry={() => void servers.refetch()}>{errorMessage(servers.error)}</ErrorNote>}
      {servers.data && servers.data.length === 0 && (
        <div className="panel pb-10">
          <Empty icon="servers">Todavía no hay servidores cargados.</Empty>
          {canManage && (
            <div className="flex justify-center">
              <Link to="/monitoreo/settings/servers" className="btn btn-primary">
                <Icon name="plus" size={14} />
                Agregar servidor
              </Link>
            </div>
          )}
        </div>
      )}
      {servers.data && servers.data.length > 0 && (
        <div className="relative max-w-md">
          <label htmlFor={searchId} className="sr-only">Buscar proyecto</label>
          <Icon name="search" size={14} className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-faint" />
          <input
            id={searchId}
            type="search"
            className="input pl-9"
            placeholder="Buscar proyecto, ruta o dominio…"
            value={query}
            onChange={(event) => setParams(event.target.value ? { q: event.target.value } : {}, { replace: true })}
          />
        </div>
      )}
      {query.trim() && <ProjectSearch query={query} />}
      {servers.data && servers.data.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {sortServers(servers.data).map((server, index) => (
            <ServerCard key={server.id} server={server} now={now} index={index} />
          ))}
        </div>
      )}
    </div>
  )
}

function StatusPills({ servers }: { servers: ServerSummary[] }) {
  const counts = servers.reduce<Record<ServerStatus, number>>(
    (acc, server) => ({ ...acc, [server.status]: acc[server.status] + 1 }),
    { online: 0, agent_down: 0, down: 0, unknown: 0, disabled: 0 },
  )
  const alerts = servers.reduce((sum, server) => sum + server.active_alerts, 0)

  return (
    <div className="fade-in flex flex-wrap gap-2">
      <dl className="contents">
        {(Object.keys(counts) as ServerStatus[])
          .filter((status) => counts[status] > 0)
          .map((status) => (
            <div key={status} className={pill}>
              <span className="size-2 rounded-full" style={{ background: toneColor(serverStatusMeta[status].tone) }} />
              <dt className="text-muted">{serverStatusMeta[status].label}</dt>
              <dd className="num font-semibold">{counts[status]}</dd>
            </div>
          ))}
      </dl>
      <Link to="/monitoreo/alerts" className={`${pill} transition-colors hover:bg-surface-2`}>
        <span className="text-muted">Alertas activas</span>
        <span className={`num font-semibold ${alerts > 0 ? 'text-crit' : ''}`}>{alerts}</span>
        <Icon name="chevron" size={12} className="text-faint" />
      </Link>
    </div>
  )
}
