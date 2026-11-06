import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { apiUrl, errorMessage } from '../api/monitoringApi'
import { useReport, useReportMessage } from '../api/useReports'
import type { ReportDetail } from '../api/types'
import { Icon } from '../components/Icon'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime, formatRelative } from '../lib/format'
import { reportStatusMeta, urgencyMeta } from '../lib/reports'
import { BackLink } from '../clients/ClientPage'
import { useFloatingReports } from './floating'
import { ErrorsPanel, LiveCheckPanel, OpenIssuesPanel } from './ReportPanels'
import { RoadmapButton } from './Roadmap'
import { ReportThread } from './ReportThread'
import { ServersPanel } from './ServersAtReport'
import { StatusButtons } from './StatusButtons'

export function ReportPage() {
  const id = Number(useParams().id)
  const report = useReport(id)
  const floating = useFloatingReports()
  if (report.isPending) return <Spinner label="Cargando el reporte…" />
  if (report.isError) return <ErrorNote onRetry={() => void report.refetch()}>{errorMessage(report.error)}</ErrorNote>
  const data = report.data
  const urgency = urgencyMeta[data.urgency]

  return (
    <div className="rise space-y-5">
      <BackLink to="/monitoreo/reportes">Reportes</BackLink>
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="title">Reporte #{data.id}</h1>
        <Badge tone={urgency.tone}>{urgency.label}</Badge>
        <Badge tone={reportStatusMeta[data.status].tone}>{reportStatusMeta[data.status].label}</Badge>
      </div>

      <div className="flex flex-wrap items-start justify-between gap-3">
        <StatusButtons report={data} />
        <div className="flex flex-wrap items-center gap-2">
          <RoadmapButton reportId={data.id} />
          <button type="button" className="btn btn-sm" onClick={() => floating.open(data.id)} title="Queda abierto mientras mirás otras pantallas">
            <Icon name="float" size={14} />
            Abrir flotante
          </button>
        </div>
      </div>

      <div className="grid gap-5 xl:grid-cols-[1fr_22rem]">
        <Panel title="Qué pasa">
          <p className="whitespace-pre-line">{data.description}</p>
          <dl className="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <Fact label="Cliente y proyecto">
              <Link to={`/monitoreo/clientes/${data.client_id}/proyectos/${data.project_id}`} className="link">
                {data.client_name} · {data.project_name}
              </Link>
            </Fact>
            <Fact label="Dónde">{data.component_label ?? 'No sabe'}</Fact>
            <Fact label="Quién">
              {data.user_email ? (
                <a href={`mailto:${data.user_email}?subject=${encodeURIComponent(`Re: tu reporte #${data.id}`)}`} className="link">
                  {data.user_email}
                </a>
              ) : (
                'usuario borrado'
              )}
            </Fact>
            <Fact label="Cuándo">
              <span title={formatDateTime(data.created_at)}>{formatRelative(data.created_at)}</span>
            </Fact>
          </dl>
          {data.user_email && (
            <a href={`mailto:${data.user_email}?subject=${encodeURIComponent(`Re: tu reporte #${data.id}`)}`} className="btn mt-5">
              Responder por mail
            </a>
          )}
        </Panel>
        <ScreenshotPanel report={data} />
      </div>

      <ChatPanel report={data} />

      <LiveCheckPanel report={data} />
      {data.server_snapshot?.servers.length ? <ServersPanel snapshot={data.server_snapshot} /> : null}
      {data.open_issues.length > 0 && <OpenIssuesPanel report={data} />}
      <ErrorsPanel report={data} />
      <BrowserPanel report={data} />
    </div>
  )
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="mt-0.5">{children}</dd>
    </div>
  )
}

// El chat con el cliente (D-049): lo que escribe el equipo le llega a su portal como "Equipo nexosmart".
function ChatPanel({ report }: { report: ReportDetail }) {
  const send = useReportMessage(report.id)
  return (
    <Panel title="Conversación con el cliente">
      <ReportThread
        mine="team"
        original={{ author: report.user_email ?? 'usuario borrado', created_at: report.created_at, description: report.description, screenshot_url: report.screenshot_url }}
        messages={report.messages}
        send={send}
      />
    </Panel>
  )
}

function ScreenshotPanel({ report }: { report: ReportDetail }) {
  return (
    <Panel title="Captura">
      {report.screenshot_url ? (
        <a href={apiUrl(report.screenshot_url)} target="_blank" rel="noopener noreferrer" className="block" title="Abrir en tamaño completo">
          <img src={apiUrl(report.screenshot_url)} alt={`Captura del reporte #${report.id}`} className="max-h-80 w-full rounded-lg bg-surface-2 object-contain" />
        </a>
      ) : (
        <p className="text-sm text-muted">El cliente no adjuntó captura.</p>
      )}
    </Panel>
  )
}

function BrowserPanel({ report }: { report: ReportDetail }) {
  const browser = report.browser
  const facts: [string, string][] = [
    ['Página', browser.page_url],
    ['Navegador', browser.user_agent],
    ['Pantalla', browser.screen],
    ['Zona horaria', browser.timezone],
    ['Idioma', browser.language],
    ['IP', report.ip],
  ]
  return (
    <Panel title="Desde dónde reportó">
      <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
        {facts.map(([label, value]) => (
          <Fact key={label} label={label}>
            <span className="font-mono text-xs break-all">{value || '—'}</span>
          </Fact>
        ))}
      </dl>
      <p className="mt-4 flex items-center gap-1.5 text-xs text-faint">
        <Icon name="alert" size={12} />
        Solo lo ve el equipo: el cliente nunca ve detalles técnicos.
      </p>
    </Panel>
  )
}
