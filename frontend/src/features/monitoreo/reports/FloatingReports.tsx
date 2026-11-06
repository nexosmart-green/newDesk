import { skipToken, useQuery } from '@tanstack/react-query'
import { useEffect, useMemo, useRef, useState, type CSSProperties, type PointerEvent, type ReactNode } from 'react'
import { Link } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { keys } from '../api/useMonitoring'
import { useReport, useReportMessage } from '../api/useReports'
import type { ReportDetail } from '../api/types'
import { Icon } from '../components/Icon'
import { ErrorNote, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { reportStatusMeta, urgencyMeta } from '../lib/reports'
import { DESKTOP } from '../lib/useMediaQuery'
import {
  clamp, FloatingContext, focusWindow, loadWindows, openWindow, saveWindows, useFloatingReports, type FloatingApi, type FloatingWindow,
} from './floating'
import { RoadmapButton } from './Roadmap'
import { StatusButtons } from './StatusButtons'
import { ReportThread } from './ReportThread'

const viewport = () => ({ width: window.innerWidth, height: window.innerHeight })

// Va en el Layout del equipo, por fuera del <Outlet>: las ventanas sobreviven a la navegación
// y, por localStorage, a una recarga.
export function FloatingReportsProvider({ children }: { children: ReactNode }) {
  const [windows, setWindows] = useState<FloatingWindow[]>(loadWindows)
  useEffect(() => saveWindows(windows), [windows])

  const api = useMemo<FloatingApi>(
    () => ({
      windows,
      open: (id) => setWindows((current) => openWindow(current, id, viewport())),
      close: (id) => setWindows((current) => current.filter((each) => each.id !== id)),
      toggleMinimized: (id) =>
        setWindows((current) => {
          const target = current.find((each) => each.id === id)
          return target?.minimized ? openWindow(current, id, viewport()) : current.map((each) => (each.id === id ? { ...each, minimized: true } : each))
        }),
      focus: (id) => setWindows((current) => focusWindow(current, id)),
      move: (id, x, y) => setWindows((current) => current.map((each) => (each.id === id ? { ...each, ...clamp(x, y, viewport()) } : each))),
    }),
    [windows],
  )

  return (
    <FloatingContext value={api}>
      {children}
      <FloatingLayer windows={windows} />
    </FloatingContext>
  )
}

function FloatingLayer({ windows }: { windows: FloatingWindow[] }) {
  const minimized = windows.filter((each) => each.minimized)
  // z-index acotado (50 + su lugar en la pila): z crece con cada foco y no debe tapar los cajones del Layout.
  const stack = [...windows].sort((a, b) => a.z - b.z).map((each) => each.id)
  return (
    <>
      {windows
        .filter((each) => !each.minimized)
        .map((each) => (
          <FloatingReport key={each.id} win={each} layer={50 + stack.indexOf(each.id)} />
        ))}
      {minimized.length > 0 && (
        <nav aria-label="Reportes minimizados" className="fixed bottom-3 left-3 z-[60] flex flex-wrap gap-2 lg:left-[17rem]">
          {minimized.map((each) => (
            <DockTab key={each.id} id={each.id} />
          ))}
        </nav>
      )}
    </>
  )
}

// Lee el reporte del cache sin pedirlo: pedirlo lo marcaría leído estando minimizado.
function DockTab({ id }: { id: number }) {
  const { toggleMinimized, close } = useFloatingReports()
  const cached = useQuery<ReportDetail>({ queryKey: keys.report(id), queryFn: skipToken })
  return (
    <span className="panel flex items-center shadow-[var(--shadow-pop)]">
      <button type="button" className="btn btn-ghost btn-sm max-w-[14rem]" onClick={() => toggleMinimized(id)} title="Restaurar">
        <Icon name="float" size={14} />
        <span className="truncate">#{id}{cached.data && ` · ${cached.data.project_name}`}</span>
      </button>
      <button type="button" className="btn btn-ghost btn-sm size-8 px-0" aria-label={`Cerrar el reporte #${id}`} onClick={() => close(id)}>
        <Icon name="close" size={12} />
      </button>
    </span>
  )
}

function FloatingReport({ win, layer }: { win: FloatingWindow; layer: number }) {
  const { close, toggleMinimized, focus, move } = useFloatingReports()
  const report = useReport(win.id)
  const drag = useRef<{ dx: number; dy: number } | null>(null)
  const title = report.data ? `#${win.id} · ${report.data.client_name} · ${report.data.project_name}` : `Reporte #${win.id}`

  // Se arrastra de la barra de título, solo en escritorio (en celular es una hoja abajo).
  const onPointerDown = (event: PointerEvent<HTMLElement>) => {
    if (event.button !== 0 || (event.target as HTMLElement).closest('button, a') || !window.matchMedia(DESKTOP).matches) return
    drag.current = { dx: event.clientX - win.x, dy: event.clientY - win.y }
    event.currentTarget.setPointerCapture(event.pointerId)
  }
  const onPointerMove = (event: PointerEvent<HTMLElement>) => {
    if (drag.current) move(win.id, event.clientX - drag.current.dx, event.clientY - drag.current.dy)
  }
  const endDrag = () => {
    drag.current = null
  }

  return (
    <section
      role="dialog"
      aria-label={title}
      style={{ '--x': `${win.x}px`, '--y': `${win.y}px`, zIndex: layer } as CSSProperties}
      className="panel fade-in fixed inset-x-2 bottom-2 flex max-h-[70dvh] flex-col shadow-[var(--shadow-pop)] lg:inset-x-auto lg:bottom-auto lg:left-(--x) lg:top-(--y) lg:max-h-[min(36rem,calc(100dvh-2rem))] lg:w-[24rem]"
      onPointerDownCapture={() => focus(win.id)}
      onKeyDown={(event) => event.key === 'Escape' && toggleMinimized(win.id)}
    >
      <header
        className="flex touch-none items-center gap-1 border-b border-line py-1.5 pr-1.5 pl-3 select-none lg:cursor-move"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={endDrag}
        onPointerCancel={endDrag}
      >
        <h2 className="min-w-0 flex-1 truncate text-sm font-semibold" title={title}>{title}</h2>
        <Link to={`/monitoreo/reportes/${win.id}`} className="btn btn-ghost btn-sm size-7 px-0" aria-label="Ver el reporte completo" title="Ver completo">
          <Icon name="chevron" size={14} />
        </Link>
        <button type="button" className="btn btn-ghost btn-sm size-7 px-0" aria-label="Minimizar" title="Minimizar" onClick={() => toggleMinimized(win.id)}>
          <Icon name="minimize" size={14} />
        </button>
        <button type="button" className="btn btn-ghost btn-sm size-7 px-0" aria-label="Cerrar" title="Cerrar" onClick={() => close(win.id)}>
          <Icon name="close" size={14} />
        </button>
      </header>
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
        {report.isPending ? (
          <Spinner label="Cargando el reporte…" />
        ) : report.isError ? (
          <ErrorNote onRetry={() => void report.refetch()}>{errorMessage(report.error)}</ErrorNote>
        ) : (
          <CompactReport report={report.data} />
        )}
      </div>
    </section>
  )
}

// La versión compacta: urgencia, estado, roadmap y el chat (que arranca con lo que reportó y su captura).
function CompactReport({ report }: { report: ReportDetail }) {
  const send = useReportMessage(report.id)
  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        <Badge tone={urgencyMeta[report.urgency].tone}>{urgencyMeta[report.urgency].label}</Badge>
        <Badge tone={reportStatusMeta[report.status].tone}>{reportStatusMeta[report.status].label}</Badge>
        {report.component_label && <span className="tag">{report.component_label}</span>}
        <span className="flex-1" />
        <RoadmapButton reportId={report.id} compact />
      </div>
      <StatusButtons report={report} />
      <ReportThread
        mine="team"
        original={{ author: report.user_email ?? 'usuario borrado', created_at: report.created_at, description: report.description, screenshot_url: report.screenshot_url }}
        messages={report.messages}
        send={send}
      />
    </>
  )
}
