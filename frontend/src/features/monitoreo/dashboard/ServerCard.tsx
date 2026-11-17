import { Link } from 'react-router'
import type { ServerSummary } from '../api/types'
import { CopyButton } from '../components/CopyButton'
import { Meter } from '../components/Meter'
import { ServerStatusBadge } from '../components/Status'
import { formatDuration, formatRelative, hostPort } from '../lib/format'

interface ServerCardProps {
  server: ServerSummary
  now?: Date
  index?: number
}

export function ServerCard({ server, now, index = 0 }: ServerCardProps) {
  const stale = server.status !== 'online'
  const metrics = server.metrics

  return (
    <article
      className="panel rise relative flex flex-col transition-shadow duration-[var(--dur-2)] hover:shadow-[var(--shadow-pop)]"
      style={{ animationDelay: `${Math.min(index, 8) * 40}ms` }}
      aria-label={`Servidor ${server.name}`}
    >
      <header className="flex items-start justify-between gap-3 px-5 pt-5 pb-4">
        <div className="min-w-0">
          {/* Link estirado: toda la card abre el detalle; el chip y el link de alertas quedan por encima (z-[1]). */}
          <Link to={`/monitoreo/servers/${server.id}`} className="block truncate text-lg font-semibold tracking-tight after:absolute after:inset-0 after:rounded-2xl after:content-['']">
            {server.name}
          </Link>
          <div className="mt-1.5 flex flex-col items-start gap-1.5">
            <span className="relative z-[1] max-w-full">
              <CopyButton variant="chip" text={hostPort(server.ip, server.ssh_port)} title="Copiar IP:puerto SSH" />
            </span>
            {/* Línea reservada aunque no haya hostname: todas las cards alinean sus medidores. */}
            <span className="block h-4 max-w-full truncate font-mono text-xs text-muted">{server.hostname ?? server.os ?? ''}</span>
          </div>
        </div>
        <ServerStatusBadge status={server.status} />
      </header>

      <div className={`grid flex-1 grid-cols-2 gap-x-6 gap-y-4 px-5 pb-5 transition-opacity duration-[var(--dur-3)] ${stale ? 'opacity-50' : ''}`}>
        <Meter label="CPU" percent={metrics?.cpu_percent} />
        <Meter label="RAM" percent={metrics?.memory_percent} />
        <Meter label="Swap" percent={metrics?.swap_percent} warn={60} crit={85} />
        <Meter label="Disco" percent={metrics?.disk_percent} warn={85} crit={95} />
      </div>

      <footer className="num flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-line px-5 py-3 text-xs text-muted">
        <span title="Load average (1 min)">load {metrics ? metrics.load1.toFixed(2) : '—'}</span>
        <span title="Uptime">up {formatDuration(metrics?.uptime_seconds)}</span>
        <span className="flex-1 text-right" title={server.last_seen_at ?? undefined}>
          {server.status === 'disabled' ? 'sin polling' : formatRelative(server.last_seen_at, now)}
        </span>
        {server.active_alerts > 0 && (
          <Link to={`/monitoreo/alerts?server=${server.id}`} className="relative z-[1] rounded-full bg-crit/10 px-2 py-0.5 font-medium text-crit transition-colors hover:bg-crit/16">
            {server.active_alerts} {server.active_alerts === 1 ? 'alerta' : 'alertas'}
          </Link>
        )}
      </footer>
    </article>
  )
}

// Mismas medidas que la card real: al llegar los datos no se mueve nada.
export function ServerCardSkeleton() {
  return (
    <div className="panel flex flex-col" aria-hidden="true">
      <div className="flex items-start justify-between gap-3 px-5 pt-5 pb-4">
        <div className="min-w-0 flex-1 space-y-2">
          <span className="skeleton h-6 w-32" />
          <span className="skeleton h-6 w-40 rounded-md" />
          <span className="skeleton h-3.5 w-24" />
        </div>
        <span className="skeleton h-5 w-20 rounded-full" />
      </div>
      <div className="grid grid-cols-2 gap-x-6 gap-y-4 px-5 pb-5">
        {[0, 1, 2, 3].map((index) => (
          <div key={index} className="space-y-2">
            <span className="skeleton h-3.5 w-16" />
            <span className="skeleton h-1.5 rounded-full" />
          </div>
        ))}
      </div>
      <div className="border-t border-line px-5 py-3">
        <span className="skeleton h-3.5 w-48" />
      </div>
    </div>
  )
}
