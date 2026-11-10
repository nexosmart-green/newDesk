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
    // The memorable piece of the shell: brand teal, with desk's pink as the "live" dot.
    <div
      role="group"
      aria-label="Cronómetro en curso"
      className="flex items-center gap-2 rounded-full bg-brand/15 py-1 pl-3 pr-1 text-sm ring-1 ring-brand/40"
    >
      <span aria-hidden="true" className="size-2 rounded-full bg-live motion-safe:animate-[live-pulse_1.6s_ease-in-out_infinite]" />
      <Link
        to={`/tareas/${active.task.code}`}
        title={active.task.title}
        className="hidden max-w-40 truncate font-semibold text-accent hover:underline focus-visible:outline-2 focus-visible:outline-accent sm:inline"
      >
        {active.task.code}
      </Link>
      <span role="timer" aria-label="Tiempo transcurrido" className="tabular text-base font-semibold tracking-tight">
        {formatClock(elapsed ?? 0)}
      </span>
      <button
        type="button"
        onClick={() => setStoppingId(active.id)}
        disabled={confirming}
        aria-busy={confirming || undefined}
        aria-label={`Detener cronómetro de ${active.task.code}`}
        title="Detener"
        // 36px visual inside a 44px hit area (the pill's padding absorbs the rest).
        className="relative grid size-9 cursor-pointer place-items-center rounded-full bg-brand-strong text-white transition-colors before:absolute before:-inset-1 hover:bg-danger focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-progress disabled:hover:bg-brand-strong"
      >
        {confirming ? (
          <LoaderCircle aria-hidden="true" className="size-4 animate-spin" />
        ) : (
          <Square aria-hidden="true" className="size-3.5 fill-current" />
        )}
      </button>
      {stoppingId === active.id && (
        <StopTimerDialog entry={active} onClose={() => setStoppingId(null)} />
      )}
    </div>
  )
}
