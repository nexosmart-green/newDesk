import { useState, type ReactNode } from 'react'
import { PageHeader } from '@/components/ui/PageHeader'
import { CompanyTimeNote } from '@/features/shifts/CompanyTimeNote'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { StaleContent } from '@/components/ui/StaleContent'
import { PeriodNav } from '@/components/ui/PeriodNav'
import { useSearchParamPatch, useTabParam } from '@/lib/useSearchParamPatch'
import { useCan } from '@/features/auth/useAuth'
import { SHIFTS_PERMISSION } from '@/features/shifts/types'
import { useWeekParam } from '@/lib/useWeekParam'
import { WeekNav } from '@/components/ui/WeekNav'
import { datesBetween, type Week } from '@/lib/week'
import { useCalendarFeed } from './CalendarFeed'
import { CalendarMonthView, CalendarWeekView } from './CalendarViews'
import { groupByDate, isMonthString, monthGridRange, monthLabel, monthOf, shiftMonth } from './calendarDays'
import { TeamCalendarView } from './TeamCalendarView'
import type { CalendarView } from './types'
import { useMyCalendar } from './useCalendar'
import { useTeamToday } from './useTeamCalendar'

/** `?vista=` — my week (default), my month, or the team calendar (RF-06.05). */
type PageView = CalendarView | 'equipo'

const viewLabels: Record<PageView, string> = { semana: 'Semana', mes: 'Mes', equipo: 'Equipo' }

const pageViews: readonly PageView[] = ['semana', 'mes', 'equipo']

function useMonthParam() {
  const [searchParams, patch] = useSearchParamPatch()
  const [currentMonth] = useState(() => monthOf(new Date()))
  const value = searchParams.get('mes')
  const month = isMonthString(value) ? value : currentMonth
  const { selected: view, select: setView } = useTabParam(pageViews, 'semana', 'vista')

  return { view, month, isCurrentMonth: month === currentMonth, setView, goToMonth: (next: string | null) => patch({ mes: next }) }
}

/** The team tab shows to whoever manages shifts or has people in charge (the endpoint answers 403 otherwise). */
function useCanSeeTeam(): boolean {
  const canManage = useCan(SHIFTS_PERMISSION)
  const teamToday = useTeamToday()
  return canManage || teamToday.isSuccess
}

function ViewSwitch({ views, view, onChange }: { views: PageView[]; view: PageView; onChange: (view: PageView) => void }) {
  return (
    <div role="group" aria-label="Vista" className="flex gap-1">
      {views.map((option) => (
        <Button key={option} variant={view === option ? 'primary' : 'secondary'} aria-pressed={view === option} onClick={() => onChange(option)}>
          {viewLabels[option]}
        </Button>
      ))}
    </div>
  )
}

/** RF-06.04 "Mi calendario": my shifts, my tasks due and holidays, by week or month; RF-06.05 the team's. */
export function MyCalendarPage() {
  const { week, isCurrentWeek, goToWeek } = useWeekParam()
  const { view, month, isCurrentMonth, setView, goToMonth } = useMonthParam()
  const canSeeTeam = useCanSeeTeam()
  const isTeam = view === 'equipo'
  const views: PageView[] = canSeeTeam || isTeam ? ['semana', 'mes', 'equipo'] : ['semana', 'mes']
  const viewSwitch = <ViewSwitch views={views} view={view} onChange={setView} />

  if (isTeam) {
    return (
      <section className="max-w-6xl space-y-6">
        <PageHeader title="Calendario de equipo" actions={viewSwitch} />
        <TeamCalendarView week={week} isCurrentWeek={isCurrentWeek} onWeekChange={goToWeek} />
      </section>
    )
  }
  return (
    <PersonalCalendar
      view={view === 'mes' ? 'mes' : 'semana'}
      viewSwitch={viewSwitch}
      week={week}
      isCurrentWeek={isCurrentWeek}
      onWeekChange={goToWeek}
      month={month}
      isCurrentMonth={isCurrentMonth}
      onMonthChange={goToMonth}
    />
  )
}

interface PersonalCalendarProps {
  view: CalendarView
  viewSwitch: ReactNode
  week: Week
  isCurrentWeek: boolean
  onWeekChange: (week: string | null) => void
  month: string
  isCurrentMonth: boolean
  onMonthChange: (month: string | null) => void
}

function PersonalCalendar({ view, viewSwitch, week, isCurrentWeek, onWeekChange, month, isCurrentMonth, onMonthChange }: PersonalCalendarProps) {
  const range = view === 'mes' ? monthGridRange(month) : week
  const calendar = useMyCalendar(range.from, range.to, true)
  const dates = datesBetween(range.from, range.to)
  const feed = useCalendarFeed()

  return (
    <section className="max-w-5xl space-y-6">
      <PageHeader title="Mi calendario" actions={<>{feed.actions}{viewSwitch}</>} />
      {feed.result}
      <div className="space-y-3">
        {view === 'semana' ? (
          <WeekNav week={week} isCurrentWeek={isCurrentWeek} onChange={onWeekChange} />
        ) : (
          <PeriodNav
            label="Elegir mes"
            current={monthLabel(month)}
            previousLabel="Mes anterior"
            todayLabel="Este mes"
            nextLabel="Mes siguiente"
            isToday={isCurrentMonth}
            onPrevious={() => onMonthChange(shiftMonth(month, -1))}
            onToday={() => onMonthChange(null)}
            onNext={() => onMonthChange(shiftMonth(month, 1))}
          />
        )}
        {!calendar.data ? (
          <PageState isPending={calendar.isPending} error={calendar.error} shape="table" onRetry={() => void calendar.refetch()} />
        ) : (
          <StaleContent stale={calendar.isPlaceholderData} className="space-y-3">
            <CompanyTimeNote timeZone={calendar.data.timezone} />
            {view === 'semana' ? (
              <CalendarWeekView dates={dates} days={groupByDate(calendar.data)} />
            ) : (
              <CalendarMonthView dates={dates} days={groupByDate(calendar.data)} month={month} />
            )}
          </StaleContent>
        )}
      </div>
    </section>
  )
}
