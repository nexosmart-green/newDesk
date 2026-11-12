import { CalendarPlus } from 'lucide-react'
import { Link } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { useCan } from '@/features/auth/useAuth'
import { SHIFTS_PERMISSION } from '@/features/shifts/types'
import type { Week } from '@/features/timesheet/week'
import { WeekNav } from '@/features/timesheet/WeekNav'
import { todayIn } from '@/lib/timezone'
import { TeamTodayBoard, TeamTodaySkeleton } from './TeamTodayBoard'
import { TeamWeekGrid } from './TeamWeekGrid'
import { useTeamCalendar, useTeamToday } from './useTeamCalendar'

interface TeamCalendarViewProps {
  week: Week
  isCurrentWeek: boolean
  onWeekChange: (monday: string | null) => void
}

/** RF-06.05 "Calendario de equipo": who is working now, later and today, then the week. */
export function TeamCalendarView({ week, isCurrentWeek, onWeekChange }: TeamCalendarViewProps) {
  const today = useTeamToday()
  const weekCalendar = useTeamCalendar(week.from, week.to)
  const canManage = useCan(SHIFTS_PERMISSION)
  const nobodyWorks = weekCalendar.data?.days.every((day) => day.shifts.length === 0) ?? false

  return (
    <div className="space-y-6">
      {today.data ? (
        <TeamTodayBoard calendar={today.data} />
      ) : today.isPending ? (
        <TeamTodaySkeleton />
      ) : (
        <PageState isPending={false} error={today.error} onRetry={() => void today.refetch()} />
      )}

      <section aria-labelledby="team-week-title" className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 id="team-week-title" className="text-base font-semibold">
            Semana del equipo
          </h2>
          <WeekNav week={week} isCurrentWeek={isCurrentWeek} onChange={onWeekChange} />
        </div>
        {!weekCalendar.data ? (
          <PageState isPending={weekCalendar.isPending} error={weekCalendar.error} shape="table" onRetry={() => void weekCalendar.refetch()} />
        ) : (
          <>
            {nobodyWorks && (
              <div className="flex flex-wrap items-center gap-3 rounded-xl border border-dashed border-border bg-surface px-4 py-3 text-sm">
                <span className="min-w-0 flex-1 text-muted">Nadie del equipo tiene turnos esta semana.</span>
                {canManage && (
                  <Link
                    to="/turnos"
                    className="inline-flex min-h-11 items-center gap-2 rounded-lg bg-brand-strong px-4 py-2 text-white hover:bg-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                  >
                    <CalendarPlus aria-hidden="true" className="size-4" />
                    Armar turnos
                  </Link>
                )}
              </div>
            )}
            <TeamWeekGrid calendar={weekCalendar.data} today={todayIn(weekCalendar.data.timezone)} />
            <p className="text-xs text-muted">Horarios en {weekCalendar.data.timezone}. Las licencias se suman cuando esté el módulo.</p>
          </>
        )}
      </section>
    </div>
  )
}
