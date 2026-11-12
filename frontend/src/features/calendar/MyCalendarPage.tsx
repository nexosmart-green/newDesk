import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { PeriodNav } from '@/components/ui/PeriodNav'
import { useCan } from '@/features/auth/useAuth'
import { SHIFTS_PERMISSION } from '@/features/shifts/types'
import { useWeekParam } from '@/features/timesheet/useWeekParam'
import { WeekNav } from '@/features/timesheet/WeekNav'
import { datesBetween } from '@/features/timesheet/week'
import { CalendarFeedPanel } from './CalendarFeedPanel'
import { CalendarMonthView, CalendarWeekView } from './CalendarViews'
import { groupByDate, isMonthString, monthGridRange, monthLabel, monthOf, shiftMonth } from './calendarDays'
import { TeamCalendarView } from './TeamCalendarView'
import type { CalendarView } from './types'
import { useMyCalendar } from './useCalendar'
import { useTeamToday } from './useTeamCalendar'

/** `?vista=` — my week (default), my month, or the team calendar (RF-06.05). */
type PageView = CalendarView | 'equipo'

const viewLabels: Record<PageView, string> = { semana: 'Semana', mes: 'Mes', equipo: 'Equipo' }

function useMonthParam() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [currentMonth] = useState(() => monthOf(new Date()))
  const value = searchParams.get('mes')
  const month = isMonthString(value) ? value : currentMonth
  const requested = searchParams.get('vista')
  const view: PageView = requested === 'mes' || requested === 'equipo' ? requested : 'semana'

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
    setView: (next: PageView) => update({ vista: next === 'semana' ? null : next }),
    goToMonth: (next: string | null) => update({ mes: next }),
  }
}

/** The team tab shows to whoever manages shifts or has people in charge (the endpoint answers 403 otherwise). */
function useCanSeeTeam(): boolean {
  const canManage = useCan(SHIFTS_PERMISSION)
  const teamToday = useTeamToday()
  return canManage || teamToday.isSuccess
}

/** RF-06.04 "Mi calendario": my shifts, my tasks due and holidays, by week or month; RF-06.05 the team's. */
export function MyCalendarPage() {
  const { week, isCurrentWeek, goToWeek } = useWeekParam()
  const { view, month, isCurrentMonth, setView, goToMonth } = useMonthParam()
  const canSeeTeam = useCanSeeTeam()
  const isTeam = view === 'equipo'
  const range = view === 'mes' ? monthGridRange(month) : week
  const calendar = useMyCalendar(range.from, range.to, !isTeam)
  const dates = datesBetween(range.from, range.to)
  const views: PageView[] = canSeeTeam || isTeam ? ['semana', 'mes', 'equipo'] : ['semana', 'mes']

  return (
    <section className={`${isTeam ? 'max-w-6xl' : 'max-w-5xl'} space-y-4`}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-lg font-semibold">{isTeam ? 'Calendario de equipo' : 'Mi calendario'}</h1>
        <div role="group" aria-label="Vista" className="flex gap-1">
          {views.map((option) => (
            <Button key={option} variant={view === option ? 'primary' : 'secondary'} aria-pressed={view === option} onClick={() => setView(option)}>
              {viewLabels[option]}
            </Button>
          ))}
        </div>
      </div>
      {isTeam ? (
        <TeamCalendarView week={week} isCurrentWeek={isCurrentWeek} onWeekChange={goToWeek} />
      ) : (
        <>
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
            <PageState isPending={calendar.isPending} error={calendar.error} shape="table" onRetry={() => void calendar.refetch()} />
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
        </>
      )}
    </section>
  )
}
