import type { PM2, PM2Daemon, PM2Process } from '../../api/contract'
import { CopyButton } from '../../components/CopyButton'
import { DataTable, Pagination } from '../../components/DataTable'
import { Empty, ErrorNote, Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'
import { processTone } from '../../lib/status'
import { formatBytes, formatPercent, formatRelative } from '../../lib/format'
import { usePage } from '../../lib/paginate'

export function Pm2Panel({ data }: { data: PM2 }) {
  const daemons = data.daemons ?? []
  if (daemons.length === 0) return <Empty>No hay daemons de PM2 en este servidor.</Empty>

  return (
    <div className="space-y-4">
      {daemons.map((daemon) => (
        <Pm2Daemon key={daemon.home} daemon={daemon} />
      ))}
    </div>
  )
}

// La misma página de procesos alimenta la tabla (escritorio) y las tarjetas (celular).
function Pm2Daemon({ daemon }: { daemon: PM2Daemon }) {
  const page = usePage(daemon.processes ?? [])
  return (
    <Panel
      bodyClassName=""
      title={
        <span>
          PM2 · usuario <span className="font-mono text-[0.8125rem]">{daemon.user}</span>
        </span>
      }
      actions={
        <span className="flex items-center gap-2">
          <span className="font-mono text-xs text-faint">{daemon.home}</span>
          <Badge tone={daemon.alive ? 'ok' : 'crit'}>{daemon.alive ? `daemon ${daemon.pid}` : 'daemon caído'}</Badge>
        </span>
      }
    >
      {daemon.error && <div className="px-5 pb-4"><ErrorNote>{daemon.error}</ErrorNote></div>}
      <div className="hidden sm:block">
      <DataTable<PM2Process>
        rows={page.rows}
        rowKey={(row) => String(row.pm_id)}
        empty="Sin procesos en este daemon."
        columns={[
          { header: 'ID', cell: (row) => <span className="num text-muted">{row.pm_id}</span> },
          {
            header: 'Nombre',
            cell: (row) => (
              <div>
                <div className="font-medium">{row.name}</div>
                <div className="max-w-[260px] truncate font-mono text-xs text-faint" title={row.cwd}>{row.cwd}</div>
              </div>
            ),
          },
          { header: 'Estado', cell: (row) => <Badge tone={processTone(row.status)}>{row.status}</Badge> },
          {
            header: 'Reinicios',
            align: 'right',
            cell: (row) => (
              <span className={`num ${row.unstable_restarts > 0 ? 'text-warn' : ''}`} title={`${row.unstable_restarts} inestables`}>
                {row.restarts}
              </span>
            ),
          },
          { header: 'Memoria', align: 'right', cell: (row) => <span className="num whitespace-nowrap">{formatBytes(row.memory_bytes)}</span> },
          { header: 'CPU', align: 'right', cell: (row) => <span className="num">{formatPercent(row.cpu_percent)}</span> },
          { header: 'Arriba desde', cell: (row) => <span className="num whitespace-nowrap text-xs text-muted">{formatRelative(row.uptime_since)}</span> },
          {
            header: 'Modo',
            cell: (row) => (
              <span className="text-xs text-muted">
                {row.exec_mode.replace('_mode', '')}
                {row.instances > 1 ? ` ×${row.instances}` : ''} · node {row.node_version}
                {row.is_module && <span className="text-info"> · módulo</span>}
              </span>
            ),
          },
          { header: 'Reload', cell: (row) => (row.is_module || !row.reload_command ? <span className="text-faint">—</span> : <CopyButton variant="chip" text={row.reload_command} title={`Copiar: ${row.reload_command}`} />) },
        ]}
      />
      </div>
      <Pm2MobileList processes={page.rows} />
      <Pagination page={page.page} pages={page.pages} total={page.total} noun="procesos" onChange={page.setPage} />
    </Panel>
  )
}

// En mobile la tabla de PM2 es muy ancha: tarjetas con lo esencial y el comando a mano.
function Pm2MobileList({ processes }: { processes: PM2Process[] }) {
  if (processes.length === 0) return null
  return (
    <ul className="divide-y divide-line border-t border-line sm:hidden">
      {processes.map((row) => (
        <li key={row.pm_id} className="space-y-2 px-5 py-3">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <div className="font-medium">
                <span className="num text-faint">{row.pm_id}</span> {row.name}
              </div>
              <div className="truncate font-mono text-xs text-faint">{row.cwd}</div>
            </div>
            <Badge tone={processTone(row.status)}>{row.status}</Badge>
          </div>
          <div className="num flex flex-wrap gap-x-3 text-xs text-muted">
            <span>{formatBytes(row.memory_bytes)}</span>
            <span>CPU {formatPercent(row.cpu_percent)}</span>
            <span className={row.unstable_restarts > 0 ? 'text-warn' : ''}>{row.restarts} reinicios</span>
            <span>{formatRelative(row.uptime_since)}</span>
          </div>
          {!row.is_module && row.reload_command && <CopyButton variant="chip" text={row.reload_command} title={`Copiar: ${row.reload_command}`} />}
        </li>
      ))}
    </ul>
  )
}
