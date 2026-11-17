import { Link, useSearchParams } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { useReports } from '../api/useReports'
import type { ReportRow, ReportStatus } from '../api/types'
import { DataTable, Pagination, type Column } from '../components/DataTable'
import { Icon } from '../components/Icon'
import { Empty, ErrorNote, Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { Tabs } from '../components/Tabs'
import { formatDateTime, formatRelative } from '../lib/format'
import { pagesOf } from '../lib/paginate'
import { reportStatusMeta, urgencyMeta } from '../lib/reports'
import { DailyReportButton } from './DailyReport'
import { RoadmapButton } from './Roadmap'

type Filter = 'all' | ReportStatus

const filterTabs: { id: Filter; label: string }[] = [
  { id: 'received', label: 'Recibidos' },
  { id: 'in_review', label: 'En revisión' },
  { id: 'resolved', label: 'Resueltos' },
  { id: 'all', label: 'Todos' },
]

const columns: Column<ReportRow>[] = [
  // Primera: con el roadmap abierto la tabla se angosta y lo último queda fuera de vista.
  { header: 'Roadmap', cell: (row) => <RoadmapButton reportId={row.id} compact /> },
  { header: 'Urgencia', cell: (row) => <Badge tone={urgencyMeta[row.urgency].tone}>{urgencyMeta[row.urgency].label}</Badge> },
  {
    header: 'Reporte',
    cell: (row) => (
      <div className="min-w-[240px]">
        <Link to={`/monitoreo/reportes/${row.id}`} className="line-clamp-2 font-medium hover:underline">
          {row.description}
        </Link>
        <div className="mt-0.5 flex items-center gap-1.5 text-xs text-muted">
          {row.unread && <Badge tone="info">Mensaje nuevo</Badge>}
          <span>#{row.id}</span>
          {row.component_label && <span>· {row.component_label}</span>}
          {row.has_screenshot && (
            <span className="inline-flex items-center gap-1" title="Tiene captura">
              · <Icon name="image" size={12} /> captura
            </span>
          )}
        </div>
      </div>
    ),
  },
  {
    header: 'Cliente',
    cell: (row) => (
      <div className="text-sm whitespace-nowrap">
        <div className="font-medium">{row.client_name}</div>
        <div className="text-xs text-muted">{row.project_name}</div>
      </div>
    ),
  },
  { header: 'Estado', cell: (row) => <Badge tone={reportStatusMeta[row.status].tone}>{reportStatusMeta[row.status].label}</Badge> },
  {
    header: 'Cuándo',
    cell: (row) => (
      <span className="num text-xs whitespace-nowrap text-muted" title={formatDateTime(row.created_at)}>
        {formatRelative(row.created_at)}
      </span>
    ),
  },
]

// Lo que reportan los clientes desde el portal. Por defecto, lo que nadie miró todavía.
export function ReportsPage() {
  const [params, setParams] = useSearchParams()
  const raw = params.get('status')
  const filter: Filter = raw === 'all' || raw === 'in_review' || raw === 'resolved' ? raw : 'received'
  const page = Math.max(1, Number(params.get('page') ?? '1') || 1)
  const reports = useReports(filter === 'all' ? null : filter, page)
  const meta = reports.data?.meta
  const rows = reports.data?.data ?? []

  const update = (patch: Record<string, string | undefined>) => {
    const next = new URLSearchParams(params)
    for (const [key, value] of Object.entries(patch)) {
      if (value === undefined) next.delete(key)
      else next.set(key, value)
    }
    setParams(next, { replace: true })
  }

  return (
    <div className="rise space-y-5">
      <div className="space-y-1">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="title">Reportes</h1>
          <DailyReportButton />
        </div>
        <p className="text-sm text-muted">Lo que reportan los clientes desde su portal, con el chequeo en vivo de ese momento. Se responde en el chat de cada reporte, que el cliente ve en su portal.</p>
      </div>

      <Tabs label="Estado de los reportes" tabs={filterTabs} active={filter} onChange={(id) => update({ status: id === 'received' ? undefined : id, page: undefined })} />

      {reports.isError ? (
        <ErrorNote onRetry={() => void reports.refetch()}>{errorMessage(reports.error)}</ErrorNote>
      ) : (
        <Panel bodyClassName="">
          {!reports.isPending && rows.length === 0 ? (
            <Empty icon="check">{filter === 'received' ? 'No hay reportes nuevos.' : 'No hay reportes acá.'}</Empty>
          ) : (
            <div key={`${filter}-${meta?.page}`} className={`fade-in transition-opacity duration-[var(--dur-2)] ${reports.isPlaceholderData ? 'opacity-60' : ''}`}>
              <DataTable<ReportRow> rows={rows} rowKey={(row) => String(row.id)} loading={reports.isPending} columns={columns} noun="reportes" />
            </div>
          )}
          {meta && (
            <Pagination
              page={meta.page}
              pages={pagesOf(meta)}
              total={meta.total}
              noun="reportes"
              busy={reports.isPlaceholderData}
              onChange={(next) => update({ page: String(next) })}
            />
          )}
        </Panel>
      )}
    </div>
  )
}
