import { useSearchParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { PendingCommentDialog } from '@/features/timer/PendingCommentDialog'
import type { PendingEntrySummary } from '@/features/timer/pendingEntry'
import { formatDuration } from '@/lib/duration'
import { TimeHistoryTab } from './TimeHistoryTab'
import { TimesheetDaySection } from './TimesheetDaySection'
import type { Timesheet, TimesheetEntry } from './types'
import { useTimesheet } from './useTimesheet'
import { useWeekParam } from './useWeekParam'
import { WeekNav } from './WeekNav'

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

type WeekView = 'semana' | 'historial'

const views: TabItem<WeekView>[] = [
  { id: 'semana', label: 'Semana' },
  { id: 'historial', label: 'Historial' },
]

/** RF-05.12 "Mi semana": the Monday–Sunday timesheet, and "Historial" for any range (`?vista=historial`). */
export function MyWeekPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const view: WeekView = searchParams.get('vista') === 'historial' ? 'historial' : 'semana'

  function selectView(next: WeekView) {
    setSearchParams(
      (current) => {
        const params = new URLSearchParams(current)
        if (next === 'semana') params.delete('vista')
        else params.set('vista', next)
        return params
      },
      { replace: true },
    )
  }

  return (
    <section className="max-w-4xl space-y-4">
      <h1 className="text-lg font-semibold">Mi semana</h1>
      <Tabs label="Vistas de horas" tabs={views} selected={view} onSelect={selectView}>
        {view === 'semana' ? <WeekTimesheet /> : <TimeHistoryTab />}
      </Tabs>
    </section>
  )
}

function WeekTimesheet() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { week, isCurrentWeek, goToWeek } = useWeekParam()
  const timesheet = useTimesheet(week.from, week.to)
  const reviewing = Number(searchParams.get('revisar')) || null
  const reviewEntry = reviewing ? findEntry(timesheet.data, reviewing) : undefined

  function updateParams(changes: Record<string, string | null>) {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        for (const [key, value] of Object.entries(changes)) {
          if (value === null) next.delete(key)
          else next.set(key, value)
        }
        return next
      },
      { replace: true },
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <WeekNav week={week} isCurrentWeek={isCurrentWeek} onChange={goToWeek} />
        {timesheet.data && (
          <p className="text-sm">
            Total de la semana: <strong className="font-mono">{formatDuration(timesheet.data.total_seconds)}</strong>
          </p>
        )}
      </div>
      {!timesheet.data ? (
        <PageState isPending={timesheet.isPending} error={timesheet.error} shape="table" onRetry={() => void timesheet.refetch()} />
      ) : (
        <div className="space-y-3">
          {timesheet.data.days.map((day) => (
            <TimesheetDaySection
              key={day.date}
              day={day}
              timeZone={timesheet.data.timezone}
              onComplete={(entry) => updateParams({ revisar: String(entry.id) })}
            />
          ))}
        </div>
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
