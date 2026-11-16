import { Link } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { TaskCode } from '@/features/tasks/TaskCode'
import { formatDuration } from '@/lib/duration'
import { zonedPartsOf } from '@/lib/timezone'
import type { TimesheetDay, TimesheetEntry } from './types'

interface EntryRowProps {
  entry: TimesheetEntry
  timeZone: string
  onComplete: (entry: TimesheetEntry) => void
}

function EntryRow({ entry, timeZone, onComplete }: EntryRowProps) {
  const timeOf = (iso: string) => zonedPartsOf(iso, timeZone).time
  const end = entry.is_running || !entry.ended_at ? 'en curso' : timeOf(entry.ended_at)

  return (
    <li className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 py-2 sm:grid-cols-[6.5rem_minmax(0,1fr)_auto]">
      <span className="tabular text-sm text-muted sm:pt-px">
        {timeOf(entry.started_at)}–{end}
      </span>
      <span className="tabular row-start-1 text-right text-sm font-medium sm:col-start-3">{formatDuration(entry.seconds)}</span>
      <div className="col-span-2 min-w-0 space-y-1 sm:col-span-1 sm:col-start-2 sm:row-start-1">
        <p className="text-sm">
          <TaskCode code={entry.task.code} className="mr-1.5" />
          <Link to={`/tareas/${entry.task.code}`} className="hover:text-accent hover:underline">
            {entry.task.title}
          </Link>
        </p>
        <p className="text-xs text-muted">{entry.project}</p>
        {entry.comment_pending ? (
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="warning">Comentario pendiente</Badge>
            <Button variant="link" className="text-sm" onClick={() => onComplete(entry)} aria-label={`Completar comentario de ${entry.task.code}`}>
              Completar
            </Button>
          </div>
        ) : (
          entry.comment && <p className="whitespace-pre-line text-sm text-muted">{entry.comment}</p>
        )}
      </div>
    </li>
  )
}

/** One day of "Mi semana": what was done, in order (RF-05.12/18). The day's row in the list holds its total. */
export function TimesheetDayEntries({ day, timeZone, onComplete }: { day: TimesheetDay; timeZone: string; onComplete: (entry: TimesheetEntry) => void }) {
  return (
    <ul className="divide-y divide-line">
      {day.entries.map((entry) => (
        <EntryRow key={entry.id} entry={entry} timeZone={timeZone} onComplete={onComplete} />
      ))}
    </ul>
  )
}

export function DayTotal({ seconds }: { seconds: number }) {
  return (
    <span className="tabular text-sm font-semibold" aria-label={`Total del día ${formatDuration(seconds)}`}>
      {formatDuration(seconds)}
    </span>
  )
}
