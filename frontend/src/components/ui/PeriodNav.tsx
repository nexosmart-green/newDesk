import { Button } from './Button'

interface PeriodNavProps {
  /** Accessible name of the navigation, e.g. "Elegir semana". */
  label: string
  /** What is shown now, e.g. "2 nov – 8 nov 2026". */
  current: string
  previousLabel: string
  todayLabel: string
  nextLabel: string
  isToday: boolean
  onPrevious: () => void
  onToday: () => void
  onNext: () => void
}

/** Previous / current / next for week- or month-based screens. */
export function PeriodNav(props: PeriodNavProps) {
  return (
    <nav aria-label={props.label} className="flex flex-wrap items-center gap-2">
      <Button variant="secondary" onClick={props.onPrevious}>
        ← {props.previousLabel}
      </Button>
      <Button variant="secondary" disabled={props.isToday} onClick={props.onToday}>
        {props.todayLabel}
      </Button>
      <Button variant="secondary" onClick={props.onNext}>
        {props.nextLabel} →
      </Button>
      <span className="text-sm text-muted" aria-live="polite">
        {props.current}
      </span>
    </nav>
  )
}
