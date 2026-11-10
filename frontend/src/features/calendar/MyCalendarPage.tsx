import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { PeriodNav } from '@/components/ui/PeriodNav'
import { useWeekParam } from '@/features/timesheet/useWeekParam'
import { WeekNav } from '@/features/timesheet/WeekNav'
import { datesBetween } from '@/features/timesheet/week'
import { CalendarFeedPanel } from './CalendarFeedPanel'
import { CalendarMonthView, CalendarWeekView } from './CalendarViews'
import { groupByDate, isMonthString, monthGridRange, monthLabel, monthOf, shiftMonth } from './calendarDays'
import type { CalendarView } from './types'
import { useMyCalendar } from './useCalendar'

function useMonthParam() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [currentMonth] = useState(() => monthOf(new Date()))
  const value = searchParams.get('mes')
  const month = isMonthString(value) ? value : currentMonth
  const view: CalendarView = searchParams.get('vista') === 'mes' ? 'mes' : 'semana'

  function update(changes: Record<string, string | null>) {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        for (const [key, change] of Object.entries(changes)) {
          if (change === null) next.delete(key)
          else next.set(key, change)
        }
        return next
      },
      { replace: true },
    )
  }

  return {
    view,
    month,
    isCurrentMonth: month === currentMonth,
    setView: (next: CalendarView) => update({ vista: next === 'mes' ? 'mes' : null }),
    goToMonth: (next: string | null) => update({ mes: next }),
  }
}

/** RF-06.04 "Mi calendario": my shifts, my tasks due and holidays, by week or month. */
export function MyCalendarPage() {
  const { week, isCurrentWeek, goToWeek } = useWeekParam()
  const { view, month, isCurrentMonth, setView, goToMonth } = useMonthParam()
  const range = view === 'mes' ? monthGridRange(month) : week
  const calendar = useMyCalendar(range.from, range.to)
  const dates = datesBetween(range.from, range.to)

  return (
    <section className="max-w-5xl space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-lg font-semibold">Mi calendario</h1>
        <div role="group" aria-label="Vista" className="flex gap-1">
          {(['semana', 'mes'] as const).map((option) => (
            <Button key={option} variant={view === option ? 'primary' : 'secondary'} aria-pressed={view === option} onClick={() => setView(option)}>
              {option === 'semana' ? 'Semana' : 'Mes'}
            </Button>
          ))}
        </div>
      </div>
      {view === 'semana' ? (
        <WeekNav week={week} isCurrentWeek={isCurrentWeek} onChange={goToWeek} />
      ) : (
        <PeriodNav
          label="Elegir mes"
          current={monthLabel(month)}
          previousLabel="Mes anterior"
          todayLabel="Este mes"
          nextLabel="Mes siguiente"
          isToday={isCurrentMonth}
          onPrevious={() => goToMonth(shiftMonth(month, -1))}
          onToday={() => goToMonth(null)}
          onNext={() => goToMonth(shiftMonth(month, 1))}
        />
      )}
      {!calendar.data ? (
        <PageState isPending={calendar.isPending} error={calendar.error} />
      ) : (
        <>
          <p className="text-xs text-muted">Horarios de turno en {calendar.data.timezone}.</p>
          {view === 'semana' ? (
            <CalendarWeekView dates={dates} days={groupByDate(calendar.data)} />
          ) : (
            <CalendarMonthView dates={dates} days={groupByDate(calendar.data)} month={month} />
          )}
        </>
      )}
      <CalendarFeedPanel />
    </section>
  )
}
