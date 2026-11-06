import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { PendingCommentDialog } from '@/features/timer/PendingCommentDialog'
import type { PendingEntrySummary } from '@/features/timer/pendingEntry'
import { formatDuration } from '@/lib/duration'
import { TimesheetDaySection } from './TimesheetDaySection'
import type { Timesheet, TimesheetEntry } from './types'
import { useTimesheet } from './useTimesheet'
import { isLocalDateString, parseLocalDate, shiftWeek, weekLabel, weekOf } from './week'

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

/** RF-05.12 "Mi semana": personal timesheet Monday–Sunday with daily totals. */
export function MyWeekPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const weekParam = searchParams.get('semana')
  const [currentWeek] = useState(() => weekOf(new Date()))
  const week = isLocalDateString(weekParam) ? weekOf(parseLocalDate(weekParam)) : currentWeek
  const isCurrentWeek = week.from === currentWeek.from
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

  const goToWeek = (monday: string | null) => updateParams({ semana: monday })

  return (
    <section className="mx-auto max-w-3xl space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-lg font-semibold">Mi semana</h1>
        {timesheet.data && (
          <p className="text-sm">
            Total de la semana: <strong className="font-mono">{formatDuration(timesheet.data.total_seconds)}</strong>
          </p>
        )}
      </div>
      <nav aria-label="Elegir semana" className="flex flex-wrap items-center gap-2">
        <Button variant="secondary" onClick={() => goToWeek(shiftWeek(week.from, -1).from)}>
          ← Semana anterior
        </Button>
        <Button variant="secondary" disabled={isCurrentWeek} onClick={() => goToWeek(null)}>
          Esta semana
        </Button>
        <Button variant="secondary" onClick={() => goToWeek(shiftWeek(week.from, 1).from)}>
          Semana siguiente →
        </Button>
        <span className="text-sm text-muted" aria-live="polite">
          {weekLabel(week)}
        </span>
      </nav>
      {!timesheet.data ? (
        <PageState isPending={timesheet.isPending} error={timesheet.error} />
      ) : (
        <div className="space-y-3">
          {timesheet.data.days.map((day) => (
            <TimesheetDaySection
              key={day.date}
              day={day}
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
    </section>
  )
}
