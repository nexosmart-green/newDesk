import { useState } from 'react'
import { Link } from 'react-router'
import { formatClock } from '@/lib/duration'
import { StopTimerDialog } from './StopTimerDialog'
import { useElapsedSeconds } from './useElapsedSeconds'
import { useTimerState, useTimerTabSync } from './useTimer'

/** RF-05.01: the running timer, visible across the whole app (header). */
export function GlobalTimer() {
  useTimerTabSync()
  const timer = useTimerState()
  const [stopping, setStopping] = useState(false)
  const active = timer.data?.active ?? null
  const elapsed = useElapsedSeconds(active?.elapsed_seconds ?? null, timer.dataUpdatedAt)

  if (!active) return null

  return (
    <div
      role="group"
      aria-label="Cronómetro en curso"
      className="flex items-center gap-2 rounded-full border border-accent/50 bg-accent/10 py-1 pl-3 pr-1 text-sm"
    >
      <Link
        to={`/tareas/${active.task.code}`}
        title={active.task.title}
        className="font-mono font-medium uppercase text-accent hover:underline"
      >
        {active.task.code}
      </Link>
      <span role="timer" aria-label="Tiempo transcurrido" className="font-mono tabular-nums">
        {formatClock(elapsed ?? 0)}
      </span>
      <button
        type="button"
        onClick={() => setStopping(true)}
        aria-label={`Detener cronómetro de ${active.task.code}`}
        className="flex size-8 items-center justify-center rounded-full bg-danger text-white focus-visible:outline-2 focus-visible:outline-accent"
      >
        <span aria-hidden="true">■</span>
      </button>
      {stopping && <StopTimerDialog entry={active} elapsedSeconds={elapsed} onClose={() => setStopping(false)} />}
    </div>
  )
}
