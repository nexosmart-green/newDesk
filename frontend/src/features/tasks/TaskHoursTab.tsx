import { Clock } from 'lucide-react'
import { useState } from 'react'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { formatEntryRange } from '@/features/hours/entryTimes'
import { entrySourceLabels } from '@/features/timer/types'
import { formatDuration } from '@/lib/duration'
import { ManualTimeForm } from './ManualTimeForm'
import type { Task, TaskTimeEntry } from './types'
import { useTaskTimeEntries } from './useTasks'

interface TaskHoursTabProps {
  task: Task
  /** Assigned to me: I can load forgotten time here (RF-05.09). */
  canLog: boolean
}

/** Tab "Horas": every stretch worked on the task, by anyone, with its comment. Never rates. */
export function TaskHoursTab({ task, canLog }: TaskHoursTabProps) {
  const [page, setPage] = useState(1)
  const entries = useTaskTimeEntries(task.code, page)
  const timeZone = useProfileTimeZone()

  return (
    <div className="space-y-4">
      {canLog && (
        <details className="group rounded-lg border border-border bg-surface">
          <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-4 py-2 font-medium focus-visible:outline-2 focus-visible:outline-accent">
            Cargar horas a mano
            <span className="hidden text-xs font-normal text-muted group-open:hidden sm:inline">Si te olvidaste del cronómetro</span>
          </summary>
          <div className="border-t border-border p-4">
            <ManualTimeForm taskCode={task.code} projectId={task.project?.id} />
          </div>
        </details>
      )}
      {!entries.data ? (
        <PageState isPending={entries.isPending} error={entries.error} shape="list" onRetry={() => void entries.refetch()} />
      ) : entries.data.data.length === 0 ? (
        <EmptyState icon={Clock}>
          {canLog
            ? 'Todavía no hay horas en esta tarea: iniciá el cronómetro o cargalas a mano arriba.'
            : 'Todavía no hay horas en esta tarea: cuando alguien registre tiempo, aparece acá.'}
        </EmptyState>
      ) : (
        <>
          <p className="text-sm">
            Total registrado: <strong className="tabular font-semibold">{formatDuration(entries.data.meta.total_seconds)}</strong>
            {task.estimate_seconds !== null && <span className="text-muted"> de {formatDuration(task.estimate_seconds)} estimadas</span>}
          </p>
          <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Registros de horas">
            {entries.data.data.map((entry) => (
              <EntryItem key={entry.id} entry={entry} timeZone={timeZone} />
            ))}
          </ul>
          <Pagination meta={entries.data.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  )
}

function EntryItem({ entry, timeZone }: { entry: TaskTimeEntry; timeZone: string }) {
  return (
    <li className="flex gap-3 p-3">
      <Avatar name={entry.user.full_name} url={entry.user.avatar?.avatar} size="sm" />
      <div className="min-w-0 flex-1 space-y-1 text-sm">
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="font-medium">{entry.user.full_name}</span>
          <span className="text-muted">{formatEntryRange(entry.started_at, entry.ended_at, timeZone)}</span>
          <Badge>{entrySourceLabels[entry.source]}</Badge>
          {entry.is_running && <Badge tone="accent">En curso</Badge>}
        </p>
        {entry.comment_pending ? (
          <Badge tone="warning">Comentario pendiente</Badge>
        ) : (
          entry.comment && <p className="whitespace-pre-line text-muted">{entry.comment}</p>
        )}
      </div>
      <span className="tabular shrink-0 text-sm font-semibold">{formatDuration(entry.seconds)}</span>
    </li>
  )
}
