import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { PeriodNav } from '@/components/ui/PeriodNav'
import { MonthGrid } from '@/features/calendar/CalendarViews'
import { monthGridRange, monthLabel, monthOf, shiftMonth } from '@/features/calendar/calendarDays'
import { datesBetween } from '@/features/timesheet/week'
import { formatDuration } from '@/lib/duration'
import type { MyEntryFilters } from './types'
import { useDailyHours } from './useHours'

interface HoursCalendarProps {
  /** `YYYY-MM` */
  month: string
  filters: Pick<MyEntryFilters, 'user_id' | 'project_id'>
  onMonthChange: (month: string) => void
  onPickDay: (date: string) => void
}

const HOUR = 3600

/** More worked, stronger accent; the strongest still keeps the text AA on both themes. */
function fillOf(seconds: number): string {
  if (seconds >= 8 * HOUR) return 'bg-accent/50'
  if (seconds >= 6 * HOUR) return 'bg-accent/35'
  if (seconds >= 4 * HOUR) return 'bg-accent/20'
  return seconds > 0 ? 'bg-accent/10' : 'hover:bg-surface-2'
}

const totalOf = (seconds: number) => (seconds > 0 ? formatDuration(seconds) : null)

/** "Mis horas" by month: time worked per day and per week; a day opens its entries in the list. */
export function HoursCalendar({ month, filters, onMonthChange, onPickDay }: HoursCalendarProps) {
  const [currentMonth] = useState(() => monthOf(new Date()))
  const range = monthGridRange(month)
  const hours = useDailyHours({ user_id: filters.user_id, project_id: filters.project_id, ...range })
  const dates = datesBetween(range.from, range.to)
  const secondsByDate = new Map(hours.data?.days.map((day) => [day.date, day.total_seconds]))
  const sum = (some: readonly string[]) => some.reduce((total, date) => total + (secondsByDate.get(date) ?? 0), 0)

  return (
    <div className="space-y-3">
      <PeriodNav
        label="Elegir mes"
        current={monthLabel(month)}
        previousLabel="Mes anterior"
        todayLabel="Este mes"
        nextLabel="Mes siguiente"
        isToday={month === currentMonth}
        onPrevious={() => onMonthChange(shiftMonth(month, -1))}
        onToday={() => onMonthChange(currentMonth)}
        onNext={() => onMonthChange(shiftMonth(month, 1))}
      />
      {!hours.data ? (
        <PageState isPending={hours.isPending} error={hours.error} shape="table" onRetry={() => void hours.refetch()} />
      ) : (
        <>
          <p className="text-sm">
            Total del mes: <strong>{formatDuration(sum(dates.filter((date) => date.startsWith(month))))}</strong>
            <span className="text-xs text-muted"> · Días en {hours.data.timezone}</span>
          </p>
          <MonthGrid
            dates={dates}
            month={month}
            renderDay={(date) => {
              const seconds = secondsByDate.get(date) ?? 0
              const total = totalOf(seconds)
              return (
                <button
                  type="button"
                  aria-label={`Ver registros del ${Number(date.slice(8))}/${Number(date.slice(5, 7))}${total ? `: ${total}` : ''}`}
                  onClick={() => onPickDay(date)}
                  className={`min-h-16 w-full cursor-pointer rounded text-sm font-semibold tabular-nums focus-visible:outline-2 focus-visible:outline-accent ${fillOf(seconds)}`}
                >
                  {total}
                </button>
              )
            }}
            renderWeekEnd={(week) => <p className="text-sm font-semibold tabular-nums">{totalOf(sum(week))}</p>}
          />
        </>
      )}
    </div>
  )
}
