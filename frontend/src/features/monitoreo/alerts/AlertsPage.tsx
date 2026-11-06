import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { useAlerts, useServers } from '../api/useMonitoring'
import type { Alert, AlertState } from '../api/types'
import { DataTable, Pagination, type Column } from '../components/DataTable'
import { Icon } from '../components/Icon'
import { Empty, ErrorNote, Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { Tabs } from '../components/Tabs'
import { formatDateTime, formatDuration, formatRelative } from '../lib/format'
import { pagesOf } from '../lib/paginate'

const stateTabs = [
  { id: 'firing', label: 'Activas' },
  { id: 'resolved', label: 'Historial' },
] as const

function alertColumns(state: AlertState): Column<Alert>[] {
  return [
    { header: 'Severidad', cell: (row) => <Badge tone={row.severity === 'critical' ? 'crit' : 'warn'}>{row.severity === 'critical' ? 'Crítica' : 'Aviso'}</Badge> },
    {
      header: 'Alerta',
      cell: (row) => (
        <div className="min-w-[220px]">
          <div>{row.message}</div>
          <div className="mt-0.5 font-mono text-xs text-faint">
            {row.rule} · {row.target}
          </div>
        </div>
      ),
    },
    {
      header: 'Servidor',
      cell: (row) => (
        <Link to={`/monitoreo/servers/${row.server_id}`} className="font-medium hover:underline">
          {row.server_name}
        </Link>
      ),
    },
    {
      header: 'Desde',
      cell: (row) => (
        <span className="num text-xs whitespace-nowrap" title={formatDateTime(row.first_seen_at)}>
          {formatRelative(row.first_seen_at)}
        </span>
      ),
    },
    {
      header: state === 'firing' ? 'Último aviso' : 'Duró',
      cell: (row) => (
        <span className="num text-xs whitespace-nowrap text-muted">
          {state === 'firing'
            ? row.last_notified_at
              ? formatRelative(row.last_notified_at)
              : 'sin enviar'
            : formatDuration((new Date(row.resolved_at ?? row.last_seen_at).getTime() - new Date(row.first_seen_at).getTime()) / 1000)}
        </span>
      ),
    },
  ]
}

export function AlertsPage() {
  const [params, setParams] = useSearchParams()
  const state: AlertState = params.get('state') === 'resolved' ? 'resolved' : 'firing'
  const page = Math.max(1, Number(params.get('page') ?? '1') || 1)
  const serverId = Number(params.get('server')) || undefined
  const alerts = useAlerts(state, page, serverId)
  const servers = useServers()

  const update = (patch: Record<string, string | undefined>) => {
    const next = new URLSearchParams(params)
    for (const [key, value] of Object.entries(patch)) {
      if (value === undefined) next.delete(key)
      else next.set(key, value)
    }
    setParams(next, { replace: true })
  }

  const meta = alerts.data?.meta
  const columns = alertColumns(state)
  const rows = alerts.data?.data ?? []

  return (
    <div className="rise space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="title">Alertas</h1>
        <label className="flex items-center gap-2">
          <span className="label">Servidor</span>
          <span className="relative">
            <select
              className="input h-9 w-auto min-w-36"
              value={serverId ?? ''}
              onChange={(event) => update({ server: event.target.value || undefined, page: undefined })}
            >
              <option value="">Todos</option>
              {(servers.data ?? []).map((server) => (
                <option key={server.id} value={server.id}>
                  {server.name}
                </option>
              ))}
            </select>
            <Icon name="chevron" size={12} className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rotate-90 text-muted" />
          </span>
        </label>
      </div>

      <Tabs label="Estado de alertas" tabs={stateTabs} active={state} onChange={(id) => update({ state: id === 'firing' ? undefined : id, page: undefined })} />

      {alerts.isError ? (
        <ErrorNote onRetry={() => void alerts.refetch()}>{errorMessage(alerts.error)}</ErrorNote>
      ) : (
        <Panel bodyClassName="">
          {!alerts.isPending && rows.length === 0 ? (
            <Empty icon="check">{state === 'firing' ? 'No hay alertas activas.' : 'Sin alertas en el historial.'}</Empty>
          ) : (
            <div key={`${state}-${meta?.page}`} className={`fade-in transition-opacity duration-[var(--dur-2)] ${alerts.isPlaceholderData ? 'opacity-60' : ''}`}>
              <DataTable<Alert> rows={rows} rowKey={(row) => String(row.id)} loading={alerts.isPending} columns={columns} />
            </div>
          )}
          {meta && (
            <Pagination
              page={meta.page}
              pages={pagesOf(meta)}
              total={meta.total}
              noun="alertas"
              busy={alerts.isPlaceholderData}
              onChange={(next) => update({ page: String(next) })}
            />
          )}
        </Panel>
      )}
    </div>
  )
}
