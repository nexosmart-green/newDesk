import { errorMessage } from '../api/monitoringApi'
import { useRoadmap, useRoadmapAdd, useRoadmapRemove } from '../api/useReports'
import { Icon } from '../components/Icon'
import { ErrorNote, Spinner } from '../components/Panel'
import { DailyReportButton } from './DailyReport'
import { RoadmapList } from './RoadmapList'

// Roadmap de reportes de cada dev (D-064): lo que voy a resolver, en orden. Es personal (D-066): nadie más lo ve.

// Sumar o sacar un reporte de mi roadmap. compact = solo el ícono (filas de la lista, ventana flotante).
export function RoadmapButton({ reportId, compact = false }: { reportId: number; compact?: boolean }) {
  const roadmap = useRoadmap()
  const add = useRoadmapAdd()
  const remove = useRoadmapRemove()
  const inRoadmap = roadmap.data?.some((item) => item.id === reportId) ?? false
  const busy = add.isPending || remove.isPending || roadmap.isPending
  const label = inRoadmap ? 'En tu roadmap (sacar)' : 'Sumar a mi roadmap'
  const failed = add.error ?? remove.error
  return (
    <span className="inline-flex items-center gap-2">
      <button
        type="button"
        aria-pressed={inRoadmap}
        aria-label={compact ? label : undefined}
        title={label}
        disabled={busy}
        className={`btn btn-sm ${inRoadmap ? 'btn-primary' : ''} ${compact ? 'size-8 px-0' : ''}`}
        onClick={() => (inRoadmap ? remove.mutate(reportId) : add.mutate(reportId))}
      >
        {busy && !roadmap.isPending ? <Spinner /> : <Icon name={inRoadmap ? 'check' : 'roadmap'} size={14} />}
        {!compact && (inRoadmap ? 'En tu roadmap' : 'Sumar a mi roadmap')}
      </button>
      {failed && <span role="alert" className="text-xs text-crit">{errorMessage(failed)}</span>}
    </span>
  )
}

export function RoadmapPanel({ onClose }: { onClose: () => void }) {
  const roadmap = useRoadmap()
  return (
    <div className="flex h-full flex-col">
      <header className="flex items-center gap-2 px-4 pt-5 pb-3">
        <Icon name="roadmap" />
        <h2 className="flex-1 text-headline font-semibold">Mi roadmap</h2>
        <button type="button" className="btn btn-ghost btn-sm size-8 px-0" aria-label="Cerrar el roadmap" onClick={onClose}>
          <Icon name="close" size={14} />
        </button>
      </header>
      <div className="px-4 pb-2">
        <DailyReportButton />
      </div>
      {/* pt: la primera tarjeta tiene sombra y al levantarla crece; sin espacio arriba el scroll la recorta. */}
      <div className="min-h-0 flex-1 overflow-y-auto px-3 pt-1.5 pb-4">
        {roadmap.isPending ? (
          <Spinner label="Cargando el roadmap…" />
        ) : roadmap.isError ? (
          <ErrorNote onRetry={() => void roadmap.refetch()}>{errorMessage(roadmap.error)}</ErrorNote>
        ) : (
          <RoadmapList roadmap={roadmap.data} />
        )}
      </div>
    </div>
  )
}
