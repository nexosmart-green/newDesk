import { Link } from 'react-router'
import type { ServerAtReport, ServerSnapshot } from '../api/types'
import { DataTable } from '../components/DataTable'
import { Meter } from '../components/Meter'
import { Panel } from '../components/Panel'
import { Badge, ServerStatusBadge } from '../components/Status'
import { formatBytes, formatDateTime, formatDuration, formatPercent, formatRelative } from '../lib/format'

type ProcessRow = ServerAtReport['processes'][number]

// Cómo estaban los servidores del proyecto cuando el cliente reportó (D-050): métricas, alertas,
// servicios caídos y los procesos del proyecto.
export function ServersPanel({ snapshot }: { snapshot: ServerSnapshot }) {
  return (
    <Panel
      title="Servidores al reportar"
      actions={<span className="num text-xs text-muted" title={formatDateTime(snapshot.taken_at)}>{formatRelative(snapshot.taken_at)}</span>}
      bodyClassName=""
    >
      <div className="divide-y divide-line">
        {snapshot.servers.map((server) => (
          <ServerAtReportBlock key={server.id} server={server} />
        ))}
      </div>
    </Panel>
  )
}

function ServerAtReportBlock({ server }: { server: ServerAtReport }) {
  const metrics = server.metrics
  const unhealthy = server.services.filter((service) => !service.healthy)
  const healthyCount = server.services.length - unhealthy.length
  return (
    <section aria-label={`Servidor ${server.name}`} className="pt-4 first:pt-0">
      <div className="space-y-4 px-5 pb-4 text-sm">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
          <Link to={`/monitoreo/servers/${server.id}`} className="link font-semibold">{server.name}</Link>
          <ServerStatusBadge status={server.status} />
          {metrics && (
            <span className="num text-xs text-muted">
              load {metrics.load1.toFixed(2)} · up {formatDuration(metrics.uptime_seconds)}
            </span>
          )}
        </div>
        {metrics ? (
          <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
            <Meter label="CPU" percent={metrics.cpu_percent} />
            <Meter label="RAM" percent={metrics.memory_percent} />
            <Meter label="Swap" percent={metrics.swap_percent} warn={60} crit={85} />
            <Meter label="Disco" percent={metrics.disk_percent} warn={85} crit={95} />
          </div>
        ) : (
          <p className="text-muted">Sin métricas en ese momento.</p>
        )}
        <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
          <div>
            <div className="label mb-1.5">Alertas abiertas</div>
            {server.alerts.length > 0 ? (
              <ul className="space-y-1.5">
                {server.alerts.map((alert) => (
                  <li key={alert.id} className="flex items-start gap-2">
                    <Badge tone={alert.severity === 'critical' ? 'crit' : 'warn'}>{alert.severity === 'critical' ? 'Crítica' : 'Aviso'}</Badge>
                    <span className="min-w-0">{alert.message}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted">Sin alertas abiertas</p>
            )}
          </div>
          <div>
            <div className="label mb-1.5">Servicios</div>
            <div className="flex flex-wrap items-center gap-2">
              {unhealthy.map((service) => (
                <Badge key={service.name} tone="crit">{service.name} · {service.active}</Badge>
              ))}
              <span className="text-muted">{healthyCount === 1 ? '1 servicio OK' : `${healthyCount} servicios OK`}</span>
            </div>
          </div>
        </div>
      </div>
      <DataTable<ProcessRow>
        rows={server.processes}
        rowKey={(row) => `${row.kind}-${row.name}`}
        empty="El proyecto no tiene procesos en este servidor."
        noun="procesos"
        columns={[
          { header: 'Proceso', cell: (row) => <span className="font-mono text-xs">{row.name}</span> },
          { header: 'Tipo', cell: (row) => <span className="tag">{row.kind === 'pm2' ? 'PM2' : 'Supervisor'}</span> },
          { header: 'Estado', cell: (row) => <Badge tone={row.healthy ? 'ok' : 'crit'}>{row.state}</Badge> },
          { header: 'Reinicios', align: 'right', cell: (row) => <span className="num text-sm">{row.restarts ?? '—'}</span> },
          { header: 'CPU', align: 'right', cell: (row) => <span className="num text-sm">{formatPercent(row.cpu_percent)}</span> },
          { header: 'Memoria', align: 'right', cell: (row) => <span className="num text-sm">{formatBytes(row.memory_bytes)}</span> },
        ]}
      />
    </section>
  )
}
