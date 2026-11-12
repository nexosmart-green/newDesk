import { Download, SearchX } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { StaleContent } from '@/components/ui/StaleContent'
import { useCan, useProfileTimeZone } from '@/features/auth/useAuth'
import { formatEntryRange } from '@/features/hours/entryTimes'
import { useProjects } from '@/features/projects/useProjects'
import { entrySourceLabels } from '@/features/timer/types'
import { fieldErrorOf } from '@/lib/api'
import { formatDuration } from '@/lib/duration'
import { todayIn } from '@/lib/timezone'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { HistoryFiltersBar } from './HistoryFiltersBar'
import type { HistoryEntry, HistoryFilters } from './types'
import { useExportTimeHistory, useTimeHistory } from './useTimeHistory'

const ALL_PROJECTS = { client_id: null, status: '', search: '', running_out: false, page: 1 } as const

function initialFilters(today: string): HistoryFilters {
  return { from: `${today.slice(0, 8)}01`, to: today, project_id: null, search: '', user_id: null }
}

/** Mi semana → "Historial": any range (this month by default), totals, paging and the CSV for Excel. */
export function TimeHistoryTab() {
  const timeZone = useProfileTimeZone()
  const canPickPerson = useCan('time.manage')
  const [filters, setFilters] = useState(() => initialFilters(todayIn(timeZone)))
  const [page, setPage] = useState(1)
  const applied = { ...filters, search: useDebouncedValue(filters.search) }
  const rangeError = filters.from && filters.to && filters.from > filters.to ? 'La fecha «Desde» no puede ser posterior a «Hasta».' : undefined
  const ready = Boolean(filters.from && filters.to) && !rangeError
  const history = useTimeHistory(applied, page, ready)
  const exporter = useExportTimeHistory()
  const projects = useProjects(ALL_PROJECTS)

  function change(changes: Partial<HistoryFilters>) {
    setPage(1)
    setFilters((current) => ({ ...current, ...changes }))
  }

  return (
    <div className="space-y-4">
      <HistoryFiltersBar
        filters={filters}
        projects={(projects.data?.data ?? []).map((project) => ({ id: project.id, name: project.name }))}
        canPickPerson={canPickPerson}
        rangeError={rangeError}
        fromError={fieldErrorOf(history.error, 'from')}
        toError={fieldErrorOf(history.error, 'to')}
        onChange={change}
      />
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-3">
        <p className="text-sm" aria-live="polite">
          {history.data ? (
            <>
              Total: <strong className="tabular text-base font-semibold">{formatDuration(history.data.meta.total_seconds)}</strong>
              <span className="text-muted"> en {history.data.meta.total === 1 ? '1 registro' : `${history.data.meta.total} registros`}</span>
            </>
          ) : (
            <span className="text-muted">Elegí un rango para ver el total.</span>
          )}
        </p>
        <Button
          variant="secondary"
          loading={exporter.isPending}
          disabled={!ready || !history.data || history.data.meta.total === 0}
          onClick={() => exporter.mutate(applied)}
        >
          <Download aria-hidden="true" className="size-4" />
          Exportar CSV
        </Button>
      </div>
      {!ready ? null : !history.data ? (
        <PageState isPending={history.isPending} error={history.error} shape="table" onRetry={() => void history.refetch()} />
      ) : history.data.data.length === 0 ? (
        <StaleContent stale={history.isPlaceholderData} className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-6 py-10 text-center">
          <SearchX aria-hidden="true" className="size-6 text-muted" />
          <p className="font-medium">No hay horas en ese rango</p>
          <p className="max-w-sm text-sm text-muted">Probá con un rango más amplio o sacá el proyecto o la búsqueda.</p>
        </StaleContent>
      ) : (
        <StaleContent stale={history.isPlaceholderData} className="space-y-4">
          <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Registros de horas">
            {history.data.data.map((entry) => (
              <HistoryRow key={entry.id} entry={entry} timeZone={timeZone} showPerson={canPickPerson && filters.user_id !== null} />
            ))}
          </ul>
          <Pagination meta={history.data.meta} onPageChange={setPage} />
        </StaleContent>
      )}
    </div>
  )
}

function HistoryRow({ entry, timeZone, showPerson }: { entry: HistoryEntry; timeZone: string; showPerson: boolean }) {
  return (
    <li className="grid gap-1 p-3 sm:grid-cols-[13rem_minmax(0,1fr)_auto] sm:gap-4">
      <span className="tabular text-sm text-muted">{formatEntryRange(entry.started_at, entry.ended_at, timeZone)}</span>
      <div className="min-w-0 space-y-1 text-sm">
        <p>
          <Link to={`/tareas/${entry.task.code}`} className="mr-1.5 font-mono uppercase text-accent hover:underline">
            {entry.task.code}
          </Link>
          {entry.task.title}
        </p>
        <p className="flex flex-wrap items-center gap-2 text-xs text-muted">
          <span>{entry.project.name}</span>
          {showPerson && <span>{entry.user.full_name}</span>}
          <Badge>{entrySourceLabels[entry.source]}</Badge>
          {entry.is_running && <Badge tone="accent">En curso</Badge>}
        </p>
        {entry.comment_pending ? (
          <Badge tone="warning">Comentario pendiente</Badge>
        ) : (
          entry.comment && <p className="whitespace-pre-line text-muted">{entry.comment}</p>
        )}
      </div>
      <span className="tabular text-sm font-semibold sm:text-right">{formatDuration(entry.seconds)}</span>
    </li>
  )
}
