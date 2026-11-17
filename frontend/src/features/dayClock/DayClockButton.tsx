import { LoaderCircle, Split, Square, Sunrise } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { SuccessNotice } from '@/components/ui/ErrorAlert'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { useElapsedSeconds } from '@/features/timer/useElapsedSeconds'
import { useStartDay, useStopDay, useTimerState } from '@/features/timer/useTimer'
import { formatClock } from '@/lib/duration'
import { zonedPartsOf } from '@/lib/timezone'
import { SplitDayDialog } from './SplitDayDialog'

/**
 * RF-05.19 in the header, next to the ▶ timer (never both at once): start the day, watch it run, stop it
 * and split it right away. A stopped day still to split stays here in warning tone until it is.
 */
export function DayClockButton() {
  const timer = useTimerState()
  const timeZone = useProfileTimeZone()
  const start = useStartDay()
  const stop = useStopDay()
  // Bound to the clock id: a later day never reopens this dialog by itself.
  const [splittingId, setSplittingId] = useState<number | null>(null)
  const dayClock = timer.data?.day_clock ?? null
  const running = dayClock?.status === 'running'
  const elapsed = useElapsedSeconds(running ? dayClock.elapsed_seconds : null, timer.dataUpdatedAt)

  if (!timer.data || timer.data.active) return null

  if (!dayClock) {
    return (
      <Button
        variant="secondary"
        loading={start.isPending}
        onClick={() => start.mutate()}
        aria-label="Iniciar día"
        title="Iniciar el reloj del día"
        className="min-w-11 px-0 sm:px-4"
      >
        <Sunrise aria-hidden="true" className="size-4 text-accent" />
        <span className="hidden sm:inline">Iniciar día</span>
      </Button>
    )
  }

  if (running) {
    const stopAndSplit = () => {
      setSplittingId(dayClock.id)
      stop.mutate(undefined, { onError: () => setSplittingId(null) })
    }
    return (
      <div
        role="group"
        aria-label="Reloj del día"
        title={`Si te olvidás de frenarlo, se corta solo a las ${zonedPartsOf(dayClock.cutoff_at, timeZone).time}`}
        className="flex items-center gap-2 rounded-full bg-surface-2 py-1 pl-3 pr-1 text-sm ring-1 ring-border"
      >
        <Sunrise aria-hidden="true" className="size-4 shrink-0 text-accent" />
        <span className="hidden text-muted md:inline">Día en curso</span>
        <span role="timer" aria-label="Tiempo del día" className="tabular text-base font-semibold tracking-tight">
          {formatClock(elapsed ?? 0)}
        </span>
        <button
          type="button"
          onClick={stopAndSplit}
          disabled={stop.isPending}
          aria-busy={stop.isPending || undefined}
          aria-label="Frenar el reloj del día y repartirlo"
          title="Frenar y repartir el día"
          className="relative grid size-9 cursor-pointer place-items-center rounded-full bg-brand-strong text-white transition-colors before:absolute before:-inset-1 hover:bg-danger focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-progress disabled:hover:bg-brand-strong"
        >
          {stop.isPending ? <LoaderCircle aria-hidden="true" className="size-4 animate-spin" /> : <Square aria-hidden="true" className="size-3.5 fill-current" />}
        </button>
      </div>
    )
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setSplittingId(dayClock.id)}
        aria-label="Repartir día"
        title="Frenaste el reloj del día: falta repartirlo"
        className="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center gap-2 rounded-full bg-warning/15 px-3 text-sm font-semibold text-warning ring-1 ring-warning/50 transition-colors hover:bg-warning/25 focus-visible:outline-2 focus-visible:outline-accent sm:px-4"
      >
        <Split aria-hidden="true" className="size-4 shrink-0" />
        <span className="hidden sm:inline">Repartir día</span>
      </button>
      {splittingId === dayClock.id && <SplitDayDialog dayClock={dayClock} onClose={() => setSplittingId(null)} />}
    </>
  )
}

/** The way out wherever a day still to split blocks something (the ▶ refusal). */
export function SplitDayButton() {
  const dayClock = useTimerState().data?.day_clock ?? null
  const [open, setOpen] = useState(false)
  const [done, setDone] = useState(false)

  if (done) return <SuccessNotice message="Día repartido. Ya podés usar el ▶." />
  if (dayClock?.status !== 'pending') return null

  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Split aria-hidden="true" className="size-4" />
        Repartir día
      </Button>
      {open && (
        <SplitDayDialog
          dayClock={dayClock}
          onClose={() => setOpen(false)}
          onDone={() => {
            setOpen(false)
            setDone(true)
          }}
        />
      )}
    </>
  )
}
