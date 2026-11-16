import type { ReactNode } from 'react'
import { dayLabel, isWeekend, shortDayLabel } from '@/lib/week'

interface DayListProps {
  /** What the list is ("Registros de la semana"). */
  label: string
  /** Consecutive `YYYY-MM-DD` dates, in order. */
  dates: readonly string[]
  isEmpty: (date: string) => boolean
  /** A day with something in it. */
  renderDay: (date: string) => ReactNode
  /** Right side of a day's heading (its total). */
  renderTotal?: (date: string) => ReactNode
  /** Lowercase, after the dates: "sin registros", "nada agendado". */
  emptyText: string
  /** Today never merges into a run of empty days, so it can always be found. */
  today?: string
}

type Segment = { kind: 'day'; date: string } | { kind: 'empty'; dates: string[] }

/** Empty days next to each other become one run; today stands alone. */
function daySegments(dates: readonly string[], isEmpty: (date: string) => boolean, today?: string): Segment[] {
  const segments: Segment[] = []
  for (const date of dates) {
    const last = segments.at(-1)
    if (!isEmpty(date)) segments.push({ kind: 'day', date })
    else if (last?.kind === 'empty' && date !== today && last.dates.at(-1) !== today) last.dates.push(date)
    else segments.push({ kind: 'empty', dates: [date] })
  }
  return segments
}

function emptyRunLabel(dates: string[], emptyText: string, today?: string): string {
  const first = dates[0] ?? ''
  const last = dates.at(-1) ?? first
  const lastLabel = shortDayLabel(last)
  const range = dates.length === 1 ? shortDayLabel(first) : `${shortDayLabel(first)} a ${lastLabel.charAt(0).toLowerCase()}${lastLabel.slice(1)}`
  return `${range}${first === today ? ' (hoy)' : ''}: ${emptyText}`
}

/**
 * A week (or any run of days) read like a log: one container, one row per day. Days with nothing are a short
 * muted line, and several in a row collapse into one ("Mié 11 a vie 13: sin registros"). Weekends sit on a tint.
 */
export function DayList({ label, dates, isEmpty, renderDay, renderTotal, emptyText, today }: DayListProps) {
  return (
    <ol aria-label={label} className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface">
      {daySegments(dates, isEmpty, today).map((segment) => {
        if (segment.kind === 'empty') {
          const first = segment.dates[0] ?? ''
          const weekend = segment.dates.every(isWeekend)
          return (
            <li
              key={first}
              aria-current={first === today ? 'date' : undefined}
              className={`flex min-h-8 items-center px-4 py-1 text-xs text-muted ${weekend ? 'bg-surface-2/50' : ''}`}
            >
              {emptyRunLabel(segment.dates, emptyText, today)}
            </li>
          )
        }
        const { date } = segment
        const title = dayLabel(date)
        const isToday = date === today
        return (
          <li key={date} aria-current={isToday ? 'date' : undefined} className={isWeekend(date) ? 'bg-surface-2/50' : ''}>
            <section aria-label={title} className="space-y-2 px-4 py-3">
              <header className="flex items-baseline justify-between gap-3">
                <h2 className={`text-sm font-semibold first-letter:uppercase ${isWeekend(date) ? 'text-muted' : ''}`}>
                  {title}
                  {isToday && <span className="ml-2 text-xs font-medium text-accent">Hoy</span>}
                </h2>
                {renderTotal?.(date)}
              </header>
              {renderDay(date)}
            </section>
          </li>
        )
      })}
    </ol>
  )
}
