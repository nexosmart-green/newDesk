import { useState } from 'react'
import { dayLabel, toLocalDateString } from '@/features/timesheet/week'
import { CalendarDayItems } from './CalendarDayItems'
import { isEmptyDay } from './calendarDays'
import type { CalendarDay } from './types'

interface CalendarViewProps {
  dates: readonly string[]
  days: ReadonlyMap<string, CalendarDay>
}

/** Week: one section per day (stacks on mobile). */
export function CalendarWeekView({ dates, days }: CalendarViewProps) {
  return (
    <div className="space-y-3">
      {dates.map((date) => {
        const day = days.get(date)
        const title = dayLabel(date)
        return (
          <section key={date} aria-label={title} className="rounded-lg border border-border bg-surface">
            <h2 className="border-b border-border px-3 py-2 text-sm font-semibold capitalize">{title}</h2>
            <div className="px-3 py-2">
              {day && !isEmptyDay(day) ? <CalendarDayItems day={day} /> : <p className="text-xs text-muted">Nada agendado</p>}
            </div>
          </section>
        )
      })}
    </div>
  )
}

const weekdayHeaders = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

interface CalendarMonthViewProps extends CalendarViewProps {
  /** `YYYY-MM`: days outside it are dimmed. */
  month: string
}

/** Month: 7 columns; scrolls sideways inside its own box on narrow screens. */
export function CalendarMonthView({ dates, days, month }: CalendarMonthViewProps) {
  const [today] = useState(() => toLocalDateString(new Date()))

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-surface">
      <div className="grid min-w-[42rem] grid-cols-7">
        {weekdayHeaders.map((header) => (
          <div key={header} className="border-b border-border px-2 py-1 text-xs font-semibold text-muted">
            {header}
          </div>
        ))}
        {dates.map((date) => {
          const day = days.get(date)
          const inMonth = date.startsWith(month)
          return (
            <section
              key={date}
              aria-label={dayLabel(date)}
              className={`min-h-24 space-y-1 border-b border-r border-border p-1 ${inMonth ? '' : 'opacity-50'}`}
            >
              <p className={`text-xs ${date === today ? 'font-bold text-accent' : 'text-muted'}`}>{Number(date.slice(8))}</p>
              {day && !isEmptyDay(day) && <CalendarDayItems day={day} compact />}
            </section>
          )
        })}
      </div>
    </div>
  )
}
