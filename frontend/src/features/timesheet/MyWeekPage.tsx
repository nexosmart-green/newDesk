import { PageHeader } from '@/components/ui/PageHeader'
import { PageState } from '@/components/ui/PageState'
import { StaleContent } from '@/components/ui/StaleContent'
import { Link, Navigate } from 'react-router'
import { useSearchParamPatch } from '@/lib/useSearchParamPatch'
import { PendingCommentDialog } from '@/features/timer/PendingCommentDialog'
import type { PendingEntrySummary } from '@/features/timer/pendingEntry'
import { formatDuration } from '@/lib/duration'
import { DayTotal, TimesheetDayEntries } from './TimesheetDayEntries'
import type { Timesheet, TimesheetEntry } from './types'
import { useTimesheet } from './useTimesheet'
import { useWeekParam } from '@/lib/useWeekParam'
import { WeekNav } from '@/components/ui/WeekNav'
import { DayList } from '@/components/ui/DayList'
import { todayIn } from '@/lib/timezone'

function toPendingSummary(entry: TimesheetEntry): PendingEntrySummary {
  return {
    id: entry.id,
    taskCode: entry.task.code,
    taskTitle: entry.task.title,
    project: entry.project,
    startedAt: entry.started_at,
    seconds: entry.seconds,
  }
}

function findEntry(timesheet: Timesheet | undefined, id: number): TimesheetEntry | undefined {
  return timesheet?.days.flatMap((day) => day.entries).find((entry) => entry.id === id)
}

/** RF-05.12 "Mi semana": the Monday–Sunday timesheet. Any other range lives in "Mis horas". */
export function MyWeekPage() {
  const [searchParams] = useSearchParamPatch()
  // The old "Historial" tab moved to "Mis horas": keep its links working.
  if (searchParams.get('vista') === 'historial') return <Navigate to="/mis-horas" replace />

  return (
    <section className="max-w-4xl space-y-6">
      <PageHeader
        title="Mi semana"
        actions={
          <Link to="/mis-horas" className="text-sm text-accent hover:underline">
            Ver otro rango en Mis horas
          </Link>
        }
      />
      <WeekTimesheet />
    </section>
  )
}

function WeekTimesheet() {
  const [searchParams, updateParams] = useSearchParamPatch()
  const { week, isCurrentWeek, goToWeek } = useWeekParam()
  const timesheet = useTimesheet(week.from, week.to)
  const reviewing = Number(searchParams.get('revisar')) || null
  const reviewEntry = reviewing ? findEntry(timesheet.data, reviewing) : undefined

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <WeekNav week={week} isCurrentWeek={isCurrentWeek} onChange={goToWeek} />
        {timesheet.data && (
          <p className="text-sm text-muted">
            Total de la semana <strong className="tabular ml-1 text-base font-semibold text-text">{formatDuration(timesheet.data.total_seconds)}</strong>
          </p>
        )}
      </div>
      {!timesheet.data ? (
        <PageState isPending={timesheet.isPending} error={timesheet.error} shape="table" onRetry={() => void timesheet.refetch()} />
      ) : (
        <StaleContent stale={timesheet.isPlaceholderData}>
          <WeekDays timesheet={timesheet.data} onComplete={(entry) => updateParams({ revisar: String(entry.id) })} />
        </StaleContent>
      )}
      {reviewEntry?.comment_pending && (
        <PendingCommentDialog
          entry={toPendingSummary(reviewEntry)}
          onDone={() => updateParams({ revisar: null })}
          onClose={() => updateParams({ revisar: null })}
        />
      )}
    </div>
  )
}

function WeekDays({ timesheet, onComplete }: { timesheet: Timesheet; onComplete: (entry: TimesheetEntry) => void }) {
  const byDate = new Map(timesheet.days.map((day) => [day.date, day]))
  const entriesOf = (date: string) => byDate.get(date)?.entries ?? []
  return (
    <DayList
      label="Registros de la semana"
      dates={timesheet.days.map((day) => day.date)}
      today={todayIn(timesheet.timezone)}
      emptyText="sin registros"
      isEmpty={(date) => entriesOf(date).length === 0}
      renderTotal={(date) => <DayTotal seconds={byDate.get(date)?.total_seconds ?? 0} />}
      renderDay={(date) => {
        const day = byDate.get(date)
        return day && <TimesheetDayEntries day={day} timeZone={timesheet.timezone} onComplete={onComplete} />
      }}
    />
  )
}
