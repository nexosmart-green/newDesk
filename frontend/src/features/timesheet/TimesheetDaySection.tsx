import { Link } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { formatDuration } from '@/lib/duration'
import { zonedPartsOf } from '@/lib/timezone'
import type { TimesheetDay, TimesheetEntry } from './types'
import { dayLabel } from './week'

interface TimesheetDaySectionProps {
  day: TimesheetDay
  /** The timesheet's (profile) timezone: days are grouped in it, so times are shown in it too. */
  timeZone: string
  onComplete: (entry: TimesheetEntry) => void
}

interface EntryRowProps {
  entry: TimesheetEntry
  timeZone: string
  onComplete: (entry: TimesheetEntry) => void
}

function EntryRow({ entry, timeZone, onComplete }: EntryRowProps) {
  const timeOf = (iso: string) => zonedPartsOf(iso, timeZone).time
  const range = `${timeOf(entry.started_at)} – ${entry.is_running || !entry.ended_at ? 'en curso' : timeOf(entry.ended_at)}`

  return (
    <li className="space-y-1 p-3">
      <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <span className="text-sm">
          <span className="text-muted">{range}</span>{' '}
          <Link to={`/tareas/${entry.task.code}`} className="font-mono uppercase text-accent hover:underline">
            {entry.task.code}
          </Link>{' '}
          {entry.task.title}
        </span>
        <span className="font-mono text-sm tabular-nums">{formatDuration(entry.seconds)}</span>
      </div>
      <p className="text-xs text-muted">{entry.project}</p>
      {entry.comment_pending ? (
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone="warning">Comentario pendiente</Badge>
          <Button variant="link" onClick={() => onComplete(entry)} aria-label={`Completar comentario de ${entry.task.code}`}>
            Completar
          </Button>
        </div>
      ) : (
        entry.comment && <p className="whitespace-pre-line text-sm">{entry.comment}</p>
      )}
    </li>
  )
}

/** One day of "Mi semana": its total and what was done (RF-05.12/18). */
export function TimesheetDaySection({ day, timeZone, onComplete }: TimesheetDaySectionProps) {
  const title = dayLabel(day.date)
  return (
    <section aria-label={title} className="rounded-lg border border-border bg-surface">
      <header className="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
        <h2 className="text-sm font-semibold capitalize">{title}</h2>
        <span className="font-mono text-sm tabular-nums" aria-label={`Total del día ${formatDuration(day.total_seconds)}`}>
          {formatDuration(day.total_seconds)}
        </span>
      </header>
      {day.entries.length === 0 ? (
        <p className="px-3 py-2 text-xs text-muted">Sin registros</p>
      ) : (
        <ul className="divide-y divide-border">
          {day.entries.map((entry) => (
            <EntryRow key={entry.id} entry={entry} timeZone={timeZone} onComplete={onComplete} />
          ))}
        </ul>
      )}
    </section>
  )
}
