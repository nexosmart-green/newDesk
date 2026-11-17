import type { ReportDetail } from '../api/types'
import { DataTable } from '../components/DataTable'
import { ErrorNote, Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime, formatRelative } from '../lib/format'
import { partsText } from '../lib/parts'
import { portalStateMeta } from '../lib/status'

// Lo que el back juntó al llegar el reporte: el chequeo en vivo, los problemas abiertos y los errores del proyecto.

type LiveRow = ReportDetail['live_check']['components'][number]

export function LiveCheckPanel({ report }: { report: ReportDetail }) {
  const live = report.live_check
  const meta = portalStateMeta[live.status]
  return (
    <Panel
      title="Chequeo en vivo al reportar"
      actions={
        <span className="flex items-center gap-2">
          <Badge tone={meta.tone}>{meta.label}</Badge>
          <span className="num text-xs text-muted" title={formatDateTime(live.checked_at)}>{formatRelative(live.checked_at)}</span>
        </span>
      }
      bodyClassName=""
    >
      <div className="space-y-2 px-5 pb-4 text-sm">
        <p>
          <span className="text-muted">Le dijimos al cliente: </span>
          {live.message}
        </p>
        {live.parts.length > 0 && (
          <ul className="flex flex-wrap items-center gap-x-4 gap-y-1.5" aria-label="Lo que vio el cliente">
            <li className="text-muted">Lo que vio el cliente:</li>
            {live.parts.map((part) => (
              <li key={part.label} className="inline-flex items-center gap-1.5" title={part.phrase}>
                <span className="font-medium">{part.label}</span>
                <Badge tone={portalStateMeta[part.status].tone}>{portalStateMeta[part.status].label}</Badge>
              </li>
            ))}
          </ul>
        )}
      </div>
      <DataTable<LiveRow>
        rows={live.components}
        rowKey={(row) => row.label}
        empty="El proyecto no tenía componentes."
        noun="componentes"
        columns={[
          { header: 'Componente', cell: (row) => <span className="text-sm font-medium">{row.label}</span> },
          { header: 'Afecta a', cell: (row) => <span className="text-sm whitespace-nowrap">{row.parts.length > 0 ? partsText(row.parts) : '—'}</span> },
          { header: 'Estado', cell: (row) => <Badge tone={portalStateMeta[row.status].tone}>{portalStateMeta[row.status].label}</Badge> },
          { header: 'Frase', cell: (row) => <span className="text-sm">{row.phrase}</span> },
          { header: 'Detalle', cell: (row) => <span className="font-mono text-xs text-muted">{row.detail || '—'}</span> },
          { header: 'Latencia', align: 'right', cell: (row) => <span className="num text-sm">{row.latency_ms === null ? '—' : `${row.latency_ms} ms`}</span> },
          { header: 'HTTP', align: 'right', cell: (row) => <span className="num text-sm">{row.status_code ?? '—'}</span> },
        ]}
      />
    </Panel>
  )
}

export function OpenIssuesPanel({ report }: { report: ReportDetail }) {
  return (
    <Panel title="Problemas abiertos en ese momento" bodyClassName="">
      <ul className="divide-y divide-line">
        {report.open_issues.map((issue) => (
          <li key={issue.label} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-sm">
            <Badge tone={portalStateMeta[issue.status].tone}>{portalStateMeta[issue.status].label}</Badge>
            <span className="font-medium">{issue.label}</span>
            {issue.since && <span className="text-xs text-muted">desde {formatRelative(issue.since)}</span>}
            {issue.detail && <span className="font-mono text-xs text-muted">{issue.detail}</span>}
          </li>
        ))}
      </ul>
    </Panel>
  )
}

type ErrorRow = NonNullable<ReportDetail['recent_errors']>['items'][number]

export function ErrorsPanel({ report }: { report: ReportDetail }) {
  const errors = report.recent_errors
  return (
    <Panel title="Errores recientes del proyecto" bodyClassName={errors?.items.length ? '' : undefined}>
      {errors === null ? (
        <p className="text-sm text-muted">El proyecto no tiene configurado su /api/errors (se carga en el proyecto, en Clientes).</p>
      ) : errors.error ? (
        <ErrorNote>{errors.error}</ErrorNote>
      ) : errors.items.length === 0 ? (
        <p className="text-sm text-muted">Sin errores registrados hoy.</p>
      ) : (
        <DataTable<ErrorRow>
          rows={errors.items}
          rowKey={(row, index) => `${row.timestamp}-${index}`}
          noun="errores"
          columns={[
            { header: 'Cuándo', cell: (row) => <span className="num text-xs whitespace-nowrap">{row.timestamp}</span> },
            { header: 'Nivel', cell: (row) => <Badge tone={row.level === 'ERROR' ? 'crit' : 'warn'}>{row.level}</Badge> },
            {
              header: 'Error',
              cell: (row) => (
                <div className="min-w-[260px]">
                  <div className="text-sm">{row.message}</div>
                  {row.exception_class && <div className="font-mono text-xs text-faint">{row.exception_class}</div>}
                </div>
              ),
            },
            { header: 'Pedido', cell: (row) => <span className="font-mono text-xs break-all text-muted">{row.method} {row.url}</span> },
          ]}
        />
      )}
    </Panel>
  )
}
