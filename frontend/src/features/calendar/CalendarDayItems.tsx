import { Link } from 'react-router'
import { timeRange } from '@/features/shifts/shiftTime'
import { TaskCode } from '@/features/tasks/TaskCode'
import type { CalendarDay } from './types'
import { WorkModeIcon } from './WorkModeIcon'

interface CalendarDayItemsProps {
  day: CalendarDay
  /** Month cells: one line per item, truncated. */
  compact?: boolean
}

/** A day's holidays, shifts (local times + mode) and my tasks due, in that order. */
export function CalendarDayItems({ day, compact = false }: CalendarDayItemsProps) {
  const line = compact ? 'truncate text-xs' : 'text-sm'

  return (
    <ul className="space-y-1">
      {day.holidays.map((holiday) => (
        <li key={`h-${holiday.id}`} className={`${line} text-warning`}>
          Feriado: {holiday.name}
        </li>
      ))}
      {day.shifts.map((shift) => (
        <li
          key={`s-${shift.id}`}
          title={`${shift.name ?? 'Turno'} ${shift.starts_at}–${shift.ends_at}, ${shift.mode_label}`}
          className={`${line} flex items-center gap-2 rounded-sm border-l-4 bg-bg px-1.5`}
          style={{ borderLeftColor: shift.color ?? 'var(--color-border)' }}
        >
          <span className={`${compact ? 'truncate' : ''} font-medium`}>{shift.name ?? 'Turno'}</span>
          <span className="tabular shrink-0">{timeRange(shift.starts_at, shift.ends_at)}</span>
          <WorkModeIcon mode={shift.mode} withLabel={!compact} />
        </li>
      ))}
      {day.tasks.map((task) => (
        <li key={`t-${task.code}`} className={`${line} flex min-w-0 items-baseline gap-1.5`}>
          <span className="shrink-0 text-muted">Vence</span>
          <Link to={`/tareas/${task.code}`} title={task.title} className="group min-w-0 truncate hover:underline">
            <TaskCode code={task.code} className="group-hover:text-accent" />
            {!compact && ` ${task.title}`}
          </Link>
        </li>
      ))}
    </ul>
  )
}
