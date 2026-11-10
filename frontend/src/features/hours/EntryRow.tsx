import { Link } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import type { EntrySource, TimeEntry } from '@/features/timer/types'
import { formatDuration } from '@/lib/duration'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { formatEntryRange } from './entryTimes'

const sourceLabels: Record<EntrySource, string> = { timer: 'Cronómetro', manual: 'Manual', migration: 'Migración' }

interface EntryRowProps {
  entry: TimeEntry
  canChange: boolean
  onEdit: () => void
  onDelete: () => void
}

/** One worked stretch with its comment: what the client's hours went to (RF-05.18). */
export function EntryRow({ entry, canChange, onEdit, onDelete }: EntryRowProps) {
  const timeZone = useProfileTimeZone()
  return (
    <li className="grid gap-2 p-3 sm:grid-cols-[1fr_auto] sm:items-start">
      <div className="space-y-1 text-sm">
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="font-medium">{formatDuration(entry.elapsed_seconds)}</span>
          <span className="text-muted">{formatEntryRange(entry.started_at, entry.ended_at, timeZone)}</span>
          <Badge>{sourceLabels[entry.source]}</Badge>
          {entry.is_running && <Badge tone="accent">En curso</Badge>}
        </p>
        <p>
          {entry.user.full_name} ·{' '}
          <Link to={`/tareas/${entry.task.code}`} className="font-mono uppercase text-accent">
            {entry.task.code}
          </Link>{' '}
          {entry.task.title}
        </p>
        {entry.comment_pending ? (
          <Badge tone="warning">Comentario pendiente</Badge>
        ) : (
          entry.comment && <p className="whitespace-pre-line text-muted">{entry.comment}</p>
        )}
      </div>
      {canChange && (
        <div className="flex gap-2">
          <Button
            variant="secondary"
            disabled={entry.is_running}
            title={entry.is_running ? 'Detené el cronómetro antes de corregir el registro.' : undefined}
            aria-label={`Editar registro de ${entry.task.code}`}
            onClick={onEdit}
          >
            Editar
          </Button>
          <Button variant="secondary" aria-label={`Borrar registro de ${entry.task.code}`} onClick={onDelete}>
            Borrar
          </Button>
        </div>
      )}
      {canChange && entry.is_running && (
        <p className="text-xs text-muted sm:col-span-2">Está en curso: se puede corregir cuando se detenga.</p>
      )}
    </li>
  )
}
