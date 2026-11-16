import { LoaderCircle, Square } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { formatClock } from '@/lib/duration'
import { StopTimerDialog } from './StopTimerDialog'
import { isProvisional } from './timerOptimistic'
import { useElapsedSeconds } from './useElapsedSeconds'
import { useTimerState, useTimerTabSync } from './useTimer'

/** RF-05.01: the running timer, visible across the whole app (header). */
export function GlobalTimer() {
  useTimerTabSync()
  const timer = useTimerState()
  // Bound to the entry id: once that entry stops, a later timer can never reopen this dialog.
  const [stoppingId, setStoppingId] = useState<number | null>(null)
  const active = timer.data?.active ?? null
  const elapsed = useElapsedSeconds(active?.elapsed_seconds ?? null, timer.dataUpdatedAt)

  if (!active) return null
  // Just started (optimistic): stopping waits until the server confirms which entry it is.
  const confirming = isProvisional(active)

  return (
    // The one memorable piece of the shell: desk's pink means "running now" and is used for nothing else.
    <div
      role="group"
      aria-label="Cronómetro en curso"
      className="flex min-w-0 items-center gap-2 rounded-full bg-live py-1 pl-3 pr-1 text-on-live sm:gap-2.5"
    >
      <span aria-hidden="true" className="size-2 shrink-0 rounded-full bg-on-live motion-safe:animate-[live-pulse_1.6s_ease-in-out_infinite]" />
      <span role="timer" aria-label="Tiempo transcurrido" className="tabular shrink-0 text-xl font-semibold leading-none tracking-tight">
        {formatClock(elapsed ?? 0)}
      </span>
      <Link
        to={`/tareas/${active.task.code}`}
        title={`${active.task.title} (${active.task.code})`}
        className="hidden min-w-0 max-w-56 truncate text-sm font-medium underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-on-live min-[420px]:block max-sm:max-w-24"
      >
        {active.task.title}
      </Link>
      <button
        type="button"
        onClick={() => setStoppingId(active.id)}
        disabled={confirming}
        aria-busy={confirming || undefined}
        aria-label={`Detener cronómetro de ${active.task.code}`}
        title="Detener"
        // Stopping is the normal end of work, not a destructive act: never red. 32px inside a 44px hit area.
        className="relative grid size-8 shrink-0 cursor-pointer place-items-center rounded-full bg-on-live text-live transition-opacity before:absolute before:-inset-1.5 hover:opacity-85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-on-live disabled:cursor-progress"
      >
        {confirming ? (
          <LoaderCircle aria-hidden="true" className="size-4 animate-spin" />
        ) : (
          <Square aria-hidden="true" className="size-3 fill-current" />
        )}
      </button>
      {stoppingId === active.id && (
        <StopTimerDialog entry={active} onClose={() => setStoppingId(null)} />
      )}
    </div>
  )
}
