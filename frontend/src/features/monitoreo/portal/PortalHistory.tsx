import { useMemo, useState, type ReactNode } from 'react'
import { errorMessage } from '../api/monitoringApi'
import { useResponseTimes } from '../api/usePortalAdmin'
import type { Incident, IncidentNote, ResponseRange, ResponseTimes } from '../api/types'
import { Pagination } from '../components/DataTable'
import { Empty, ErrorNote, Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { Segmented } from '../components/Tabs'
import { ChartSkeleton, TimeSeriesChart } from '../components/TimeSeriesChart'
import { formatDateTime, formatDuration, formatRelative } from '../lib/format'
import { usePage } from '../lib/paginate'

// Historia del proyecto que comparten la página de estado del cliente y la del equipo. El PageSpeed está en PageSpeedPanel.

// ---------- tiempos de respuesta (2d) ----------

const rangeOptions = [
  { value: '24h', label: '24 h' },
  { value: '7d', label: '7 días' },
  { value: '30d', label: '30 días' },
] as const satisfies readonly { value: ResponseRange; label: string }[]

const rangeText: Record<ResponseRange, string> = { '24h': 'las últimas 24 h', '7d': 'los últimos 7 días', '30d': 'los últimos 30 días' }

// Las series pueden no tener los mismos instantes (una falló en un bucket): se alinean sobre la unión.
function align(data: ResponseTimes | undefined) {
  if (!data) return { xs: [] as number[], series: [] as { label: string; values: (number | null)[] }[] }
  const xs = [...new Set(data.series.flatMap((each) => each.points.map((point) => new Date(point.ts).getTime() / 1000)))].sort((a, b) => a - b)
  const series = data.series.map((each) => {
    const byTime = new Map(each.points.map((point) => [new Date(point.ts).getTime() / 1000, point.avg_ms]))
    return { label: each.label, values: xs.map((x) => byTime.get(x) ?? null) }
  })
  return { xs, series }
}

function average(points: readonly { avg_ms: number }[]): number {
  return Math.round(points.reduce((sum, point) => sum + point.avg_ms, 0) / points.length)
}

const msText = (value: number) => `${Math.round(value)} ms`

// `path` es la base del proyecto: la del cliente o la del equipo en la vista previa.
export function ResponseTimesPanel({ path }: { path: string }) {
  const [range, setRange] = useState<ResponseRange>('24h')
  const times = useResponseTimes(path, range)
  const { xs, series } = useMemo(() => align(times.data), [times.data])
  const measured = times.data?.series.filter((each) => each.points.length > 0) ?? []

  return (
    <Panel title="Tiempos de respuesta" actions={<Segmented label="Período" options={rangeOptions} value={range} onChange={setRange} />}>
      {times.isPending && <ChartSkeleton />}
      {times.isError && <ErrorNote onRetry={() => void times.refetch()}>{errorMessage(times.error)}</ErrorNote>}
      {times.data && xs.length === 0 && <Empty icon="bars">Todavía no hay mediciones en {rangeText[range]}.</Empty>}
      {times.data && xs.length > 0 && (
        <>
          <TimeSeriesChart
            title={times.data.resolution === '5m' ? 'Promedio cada 5 minutos' : 'Promedio por hora'}
            xs={xs}
            series={series}
            formatAxis={msText}
            formatValue={msText}
          />
          <p className="num mt-3 text-sm text-muted" data-testid="response-summary">
            Promedio en {rangeText[range]}: {measured.map((each) => `${each.label} ${average(each.points)} ms`).join(' · ')}
          </p>
        </>
      )}
    </Panel>
  )
}

// ---------- incidentes (3a, 3b) ----------

function duration(incident: Incident): string | null {
  if (!incident.ended_at) return null
  const seconds = (new Date(incident.ended_at).getTime() - new Date(incident.started_at).getTime()) / 1000
  return `duró ${formatDuration(seconds)}`
}

interface IncidentListProps {
  incidents: readonly Incident[]
  empty: string
  // El equipo agrega acciones: borrar una nota, sumar una nota.
  noteActions?: (note: IncidentNote) => ReactNode
  footer?: (incident: Incident) => ReactNode
}

// Lo que pasó y lo que el equipo fue contando, de la nota más vieja a la más nueva. Los abiertos van primero.
// De a 5 por página: en 90 días pueden ser decenas.
export function IncidentList({ incidents, empty, noteActions, footer }: IncidentListProps) {
  const sorted = [...incidents].sort((a, b) => Number(b.status === 'ongoing') - Number(a.status === 'ongoing'))
  const page = usePage(sorted, 5)
  if (sorted.length === 0) return <p className="px-5 py-6 text-sm text-muted">{empty}</p>
  return (
    <>
      <ul className="divide-y divide-line border-t border-line">
        {page.rows.map((incident) => {
          const ongoing = incident.status === 'ongoing'
          return (
            <li key={incident.id} className="px-5 py-4" data-testid="incident">
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone={ongoing ? 'crit' : 'ok'}>{ongoing ? 'En curso' : 'Resuelto'}</Badge>
                <span className="text-xs text-muted">{incident.component}</span>
                <span className="num ml-auto text-xs text-faint" title={formatDateTime(incident.started_at)}>
                  {[formatRelative(incident.started_at), duration(incident)].filter(Boolean).join(' · ')}
                </span>
              </div>
              <p className="mt-1.5 font-medium text-pretty">{incident.title}</p>
              {incident.notes.length > 0 && (
                <ol className="mt-3 space-y-2.5 border-l-2 border-line pl-4" aria-label="Notas del equipo">
                  {incident.notes.map((note) => (
                    <li key={note.id} className="space-y-0.5">
                      <p className="text-sm whitespace-pre-line">{note.body}</p>
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="num text-xs text-faint">
                          {formatDateTime(note.created_at)}
                          {note.author && ` · ${note.author}`}
                        </span>
                        {noteActions?.(note)}
                      </div>
                    </li>
                  ))}
                </ol>
              )}
              {footer?.(incident)}
            </li>
          )
        })}
      </ul>
      <Pagination page={page.page} pages={page.pages} total={page.total} noun="incidentes" onChange={page.setPage} />
    </>
  )
}

export function IncidentsPanel({ incidents }: { incidents: readonly Incident[] }) {
  return (
    <Panel title="Historial de incidentes (30 días)" bodyClassName="">
      <IncidentList incidents={incidents} empty="Sin incidentes en los últimos 30 días." />
    </Panel>
  )
}
