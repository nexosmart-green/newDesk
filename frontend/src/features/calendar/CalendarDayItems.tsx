import { Link } from 'react-router'
import type { CalendarDay } from './types'

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
          className={`${line} rounded border-l-4 bg-bg px-1.5`}
          style={{ borderLeftColor: shift.color ?? 'var(--color-border)' }}
        >
          <span className="font-medium">{shift.name ?? 'Turno'}</span> {shift.starts_at}–{shift.ends_at}
          <span className="text-muted"> · {shift.mode_label}</span>
        </li>
      ))}
      {day.tasks.map((task) => (
        <li key={`t-${task.code}`} className={line}>
          Vence{' '}
          <Link to={`/tareas/${task.code}`} className="font-mono uppercase text-accent hover:underline">
            {task.code}
          </Link>
          {!compact && ` ${task.title}`}
        </li>
      ))}
    </ul>
  )
}
