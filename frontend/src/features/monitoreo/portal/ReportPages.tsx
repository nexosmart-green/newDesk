import { useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import type { PortalComponent, ReportCreated } from '../api/types'
import { usePortalProject, usePortalReport, usePortalReportMessage, usePortalReports } from '../api/usePortal'
import { Icon } from '../components/Icon'
import { Empty, ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { brandStyle } from '../lib/brand'
import { formatDateTime, formatRelative } from '../lib/format'
import { reportStatusMeta, urgencyMeta } from '../lib/reports'
import { portalStateMeta, toneColor } from '../lib/status'
import { ReportThread } from '../reports/ReportThread'
import { ReportForm } from './ReportForm'

// Las pantallas de reportes comparten marca y la vuelta a la página de estado.
function ReportShell({ slug, title, children }: { slug: string; title: string; children: (components: PortalComponent[]) => ReactNode }) {
  const status = usePortalProject(slug)
  if (status.isPending) return <Spinner label="Cargando…" />
  if (status.isError) return <ErrorNote onRetry={() => void status.refetch()}>{errorMessage(status.error)}</ErrorNote>
  return (
    <div className="rise space-y-5" style={brandStyle(status.data.primary_color)}>
      <Link to={`/portal/${slug}`} className="inline-flex items-center gap-1 text-sm text-muted hover:text-text">
        <Icon name="chevron" size={12} className="rotate-180" />
        Estado de {status.data.name}
      </Link>
      <h1 className="title">{title}</h1>
      {children(status.data.components)}
    </div>
  )
}

export function PortalReportPage() {
  const { slug = '' } = useParams()
  const [result, setResult] = useState<ReportCreated | null>(null)
  return (
    <ReportShell slug={slug} title={result ? 'Recibimos tu reporte' : 'Reportar un problema'}>
      {(components) =>
        result ? (
          <ReportResult slug={slug} result={result} />
        ) : (
          <ReportForm
            slug={slug}
            components={components}
            onDone={(created) => {
              setResult(created)
              // En el celular el botón queda abajo: el resultado se lee desde arriba.
              document.getElementById('main')?.scrollIntoView?.({ block: 'start' })
            }}
          />
        )
      }
    </ReportShell>
  )
}

function ReportResult({ slug, result }: { slug: string; result: ReportCreated }) {
  const { live } = result
  const problem = live.status === 'down' || live.status === 'degraded'
  const tone = problem ? portalStateMeta[live.status].tone : 'ok'
  return (
    <div className="space-y-4">
      <div
        role="status"
        className="fade-in rounded-2xl p-5 sm:p-6"
        style={{ background: `color-mix(in oklab, ${toneColor(tone)} 10%, var(--surface))`, boxShadow: 'var(--shadow)' }}
      >
        <div className="flex items-start gap-3">
          <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full text-white" style={{ background: toneColor(tone) }} aria-hidden="true">
            <Icon name={problem ? 'alert' : 'check'} size={16} />
          </span>
          <div className="space-y-1">
            <p className="text-lg font-semibold leading-snug">{live.message}</p>
            <p className="text-sm text-muted">Reporte #{result.report.id} · lo podés seguir en "Mis reportes".</p>
          </div>
        </div>
      </div>

      {live.components.length > 0 && (
        <Panel title="Lo que vimos recién" bodyClassName="">
          <ul className="divide-y divide-line">
            {live.components.map((component) => {
              const meta = portalStateMeta[component.status]
              return (
                <li key={component.label} className="flex items-center justify-between gap-3 px-5 py-3">
                  <div className="min-w-0">
                    <div className="font-medium">{component.label}</div>
                    <div className="text-sm text-muted">{component.phrase}</div>
                  </div>
                  <Badge tone={meta.tone}>{meta.label}</Badge>
                </li>
              )
            })}
          </ul>
        </Panel>
      )}

      <div className="flex flex-col gap-2 sm:flex-row">
        <Link to={`/portal/${slug}/reportes`} className="btn btn-primary">Ver mis reportes</Link>
        <Link to={`/portal/${slug}`} className="btn">Volver al estado</Link>
      </div>
    </div>
  )
}

export function PortalReportsPage() {
  const { slug = '' } = useParams()
  return (
    <ReportShell slug={slug} title="Mis reportes">
      {() => <ReportList slug={slug} />}
    </ReportShell>
  )
}

// Un reporte con su chat con el equipo (D-049). Los mensajes nuevos llegan por Reverb (usePortalEvents).
export function PortalReportDetailPage() {
  const { slug = '', id = '' } = useParams()
  return (
    <ReportShell slug={slug} title={`Reporte #${id}`}>
      {() => <ReportDetail slug={slug} id={Number(id)} />}
    </ReportShell>
  )
}

function ReportDetail({ slug, id }: { slug: string; id: number }) {
  const detail = usePortalReport(slug, id)
  const send = usePortalReportMessage(slug, id)
  if (detail.isPending) return <Spinner label="Cargando el reporte…" />
  if (detail.isError) return <ErrorNote onRetry={() => void detail.refetch()}>{errorMessage(detail.error)}</ErrorNote>
  const { report, messages } = detail.data
  const status = reportStatusMeta[report.status]
  const urgency = urgencyMeta[report.urgency]
  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Badge tone={status.tone}>{status.label}</Badge>
        <Badge tone={urgency.tone}>{urgency.label}</Badge>
        <span className="text-xs text-muted">{report.component_label ? `En ${report.component_label}` : 'Sin indicar dónde'}</span>
        <Link to={`/portal/${slug}/reportes`} className="link ml-auto text-sm">Mis reportes</Link>
      </div>
      <Panel title="Conversación con el equipo">
        {report.status === 'resolved' && <p className="mb-4 text-sm text-muted">Lo dimos por resuelto. Si nos escribís, lo volvemos a abrir.</p>}
        <ReportThread
          mine="client"
          original={{ author: 'Tu reporte', created_at: report.created_at, description: report.description, screenshot_url: report.screenshot_url }}
          messages={messages}
          send={send}
        />
      </Panel>
    </div>
  )
}

function ReportList({ slug }: { slug: string }) {
  const reports = usePortalReports(slug)
  const report = (
    <Link to={`/portal/${slug}/reportar`} state={{ from: window.location.href }} className="btn btn-primary">
      <Icon name="alert" size={14} />
      Reportar un problema
    </Link>
  )
  if (reports.isPending) return <Spinner label="Cargando tus reportes…" />
  if (reports.isError) return <ErrorNote onRetry={() => void reports.refetch()}>{errorMessage(reports.error)}</ErrorNote>
  if (reports.data.length === 0) {
    return (
      <div className="panel space-y-4 pb-8 text-center">
        <Empty icon="check">Todavía no hay reportes de este proyecto.</Empty>
        {report}
      </div>
    )
  }
  return (
    <div className="space-y-4">
      <div>{report}</div>
      <ul className="space-y-3" aria-label="Reportes">
        {reports.data.map((item) => {
          const status = reportStatusMeta[item.status]
          const urgency = urgencyMeta[item.urgency]
          return (
            <li key={item.id}>
              <Link to={`/portal/${slug}/reportes/${item.id}`} className="panel block space-y-2 p-4 transition-colors hover:bg-surface-2 sm:p-5">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge tone={status.tone}>{status.label}</Badge>
                  <Badge tone={urgency.tone}>{urgency.label}</Badge>
                  {item.unread && <Badge tone="info">Respuesta nueva</Badge>}
                  <span className="num ml-auto text-xs text-muted" title={formatDateTime(item.created_at)}>
                    #{item.id} · {formatRelative(item.created_at)}
                  </span>
                </div>
                <p className="line-clamp-3 text-sm whitespace-pre-line">{item.description}</p>
                <p className="text-xs text-muted">
                  {item.component_label ? `En ${item.component_label}` : 'Sin indicar dónde'}
                  {item.status === 'resolved' && item.resolved_at && ` · resuelto ${formatRelative(item.resolved_at)}`}
                </p>
              </Link>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
