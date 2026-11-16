import { Square } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { StartTimerButton } from '@/features/timer/StartTimerButton'
import { StopTimerDialog } from '@/features/timer/StopTimerDialog'
import { isProvisional } from '@/features/timer/timerOptimistic'
import type { TimeEntry } from '@/features/timer/types'
import { useElapsedSeconds } from '@/features/timer/useElapsedSeconds'
import { useTimerState } from '@/features/timer/useTimer'
import { useTimeHistory } from '@/features/timesheet/useTimeHistory'
import { formatClock } from '@/lib/duration'
import { todayIn } from '@/lib/timezone'
import { toLocalDateString } from '@/lib/week'

/** How far back "the last thing you worked on" looks. */
const LOOKBACK_DAYS = 30

const band = 'flex flex-col gap-4 rounded-lg border border-line bg-surface p-4 sm:flex-row sm:items-center sm:p-5'

function daysBefore(date: string, days: number): string {
  const [year = 0, month = 1, day = 1] = date.split('-').map(Number)
  return toLocalDateString(new Date(year, month - 1, day - days))
}

/** Running: the time, big, on the work log's one pink rule, with Detener at hand. */
function Running({ entry, receivedAt }: { entry: TimeEntry; receivedAt: number }) {
  const elapsed = useElapsedSeconds(entry.elapsed_seconds, receivedAt)
  const [stopping, setStopping] = useState(false)
  const timeZone = useProfileTimeZone()

  return (
    <div className={`${band} shadow-[inset_3px_0_0_var(--color-live)]`}>
      <p
        role="timer"
        aria-label="Tiempo transcurrido"
        className="tabular shrink-0 text-[28px] font-semibold leading-none tracking-tight"
      >
        {formatClock(elapsed ?? 0)}
      </p>
      <div className="min-w-0 flex-1 space-y-0.5">
        <Link to={`/tareas/${entry.task.code}`} className="block truncate font-medium hover:underline">
          {entry.task.title}
        </Link>
        <p className="truncate text-sm text-muted">
          {entry.project.name}, desde las{' '}
          {new Date(entry.started_at).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', timeZone })}
        </p>
      </div>
      <Button variant="live" disabled={isProvisional(entry)} onClick={() => setStopping(true)}>
        <Square aria-hidden="true" className="size-3 fill-current" />
        Detener
      </Button>
      {stopping && <StopTimerDialog entry={entry} onClose={() => setStopping(false)} />}
    </div>
  )
}

/** Nothing running: pick up the last task worked on, or a quiet nudge to choose one. */
function Idle() {
  const today = todayIn(useProfileTimeZone())
  const history = useTimeHistory({ from: daysBefore(today, LOOKBACK_DAYS), to: today, project_id: null, search: '', user_id: null }, 1, true)
  // Same height as the band it becomes: the page below must not jump when the history arrives.
  if (!history.data) return <div aria-hidden="true" className="min-h-[4.5rem]" />
  const last = history.data.data[0]

  if (!last) {
    return (
      <div className={band}>
        <p className="min-w-0 flex-1 text-sm text-muted">
          El cronómetro está parado. Elegí una tarea en{' '}
          <Link to="/mis-tareas" className="text-accent hover:underline">
            Mis tareas
          </Link>{' '}
          para empezar.
        </p>
      </div>
    )
  }

  return (
    <div className={band}>
      <p className="min-w-0 flex-1 text-muted">
        Lo último que trabajaste fue{' '}
        <Link to={`/tareas/${last.task.code}`} className="font-medium text-text hover:underline">
          {last.task.title}
        </Link>
        , de {last.project.name}.
      </p>
      <StartTimerButton task={last.task} startLabel="Seguir" />
    </div>
  )
}

/** Inicio's first line: what is being timed now, or where to pick up (RF-05.01). */
export function NowBand() {
  const timer = useTimerState()
  if (!timer.data) return null
  const active = timer.data.active

  return (
    <section aria-label="Ahora">
      {active ? <Running entry={active} receivedAt={timer.dataUpdatedAt} /> : <Idle />}
    </section>
  )
}
