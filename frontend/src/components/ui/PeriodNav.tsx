import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from './Button'

interface PeriodNavProps {
  /** Accessible name of the navigation, e.g. "Elegir semana". */
  label: string
  /** What is shown now, e.g. "2 nov – 8 nov 2026". */
  current: string
  /** Names of the chevrons, e.g. "Semana anterior". */
  previousLabel: string
  /** Tooltip of "Hoy", saying where it goes (e.g. "Esta semana"). */
  todayLabel: string
  nextLabel: string
  isToday: boolean
  onPrevious: () => void
  onToday: () => void
  onNext: () => void
}

/** The period first (what you are looking at), then ‹ Hoy › to move. Week, month or year screens. */
export function PeriodNav(props: PeriodNavProps) {
  return (
    <nav aria-label={props.label} className="flex flex-wrap items-center gap-x-3 gap-y-2">
      <p className="tabular text-base font-semibold tracking-tight first-letter:uppercase" aria-live="polite">
        {props.current}
      </p>
      <div className="flex items-center gap-1">
        <Button variant="icon" aria-label={props.previousLabel} title={props.previousLabel} onClick={props.onPrevious}>
          <ChevronLeft aria-hidden="true" className="size-5" />
        </Button>
        <Button variant="secondary" disabled={props.isToday} title={props.todayLabel} onClick={props.onToday}>
          Hoy
        </Button>
        <Button variant="icon" aria-label={props.nextLabel} title={props.nextLabel} onClick={props.onNext}>
          <ChevronRight aria-hidden="true" className="size-5" />
        </Button>
      </div>
    </nav>
  )
}
