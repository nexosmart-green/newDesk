import { formatDuration } from '@/lib/duration'
import { bagLevelOf, type BagLevel, type HourBag } from './types'

const fillClass: Record<BagLevel, string> = {
  ok: 'bg-success',
  warning: 'bg-warning',
  exhausted: 'bg-danger',
}

const textClass: Record<BagLevel, string> = {
  ok: 'text-success',
  warning: 'text-warning',
  exhausted: 'text-danger',
}

function levelLabel(bag: HourBag, level: BagLevel): string {
  if (bag.is_exhausted || level === 'exhausted') return 'Sin horas disponibles'
  return level === 'warning' ? 'Horas por agotarse' : 'Horas disponibles'
}

interface HoursBarProps {
  bag: HourBag
  /** Slim version for the project list (% column). */
  compact?: boolean
}

/** RF-03.05: Bolsa / Consumidas / Disponibles, green < 80% ≤ yellow < 100% ≤ red. Never color-only. */
export function HoursBar({ bag, compact = false }: HoursBarProps) {
  if (!bag.has_cap) {
    if (compact) return <span className="text-xs text-muted">Sin tope</span>
    return (
      <dl className="grid grid-cols-3 gap-2 text-sm">
        <div>
          <dt className="text-xs text-muted">Bolsa</dt>
          <dd>Sin tope (interno)</dd>
        </div>
        <div>
          <dt className="text-xs text-muted">Consumidas</dt>
          <dd className="tabular">{formatDuration(bag.consumed_seconds)}</dd>
        </div>
      </dl>
    )
  }

  const percent = bag.percent_consumed ?? 0
  const level = bagLevelOf(bag.percent_consumed)
  const meter = (
    <div
      role="meter"
      aria-label="Horas consumidas"
      aria-valuemin={0}
      aria-valuemax={100}
      aria-valuenow={Math.min(percent, 100)}
      aria-valuetext={`${percent}% consumido`}
      data-level={level}
      className={`w-full overflow-hidden rounded-sm bg-border ${compact ? 'h-1.5' : 'h-2.5'}`}
    >
      <div className={`h-full ${fillClass[level]}`} style={{ width: `${Math.min(percent, 100)}%` }} />
    </div>
  )

  // Rows: the number and the bar; the column (and the meter's name) already say what they measure.
  if (compact) {
    return (
      <div className="flex w-full min-w-28 items-center gap-2 sm:w-32">
        <span aria-hidden="true" className={`tabular w-10 shrink-0 text-right text-xs font-medium ${textClass[level]}`}>
          {percent}%
        </span>
        {meter}
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <span className={`text-sm font-medium ${textClass[level]}`}>{levelLabel(bag, level)}</span>
        <span className="tabular text-sm text-muted">{percent}% consumido</span>
      </div>
      {meter}
      <dl className="grid grid-cols-3 gap-2 text-sm">
        <div>
          <dt className="text-xs text-muted">Bolsa</dt>
          <dd className="tabular">{formatDuration(bag.allocated_seconds ?? 0)}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted">Consumidas</dt>
          <dd className="tabular">{formatDuration(bag.consumed_seconds)}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted">Disponibles</dt>
          <dd className={`tabular ${(bag.available_seconds ?? 0) <= 0 ? 'text-danger' : ''}`}>
            {(bag.available_seconds ?? 0) < 0 ? '−' : ''}
            {formatDuration(Math.abs(bag.available_seconds ?? 0))}
          </dd>
        </div>
      </dl>
    </div>
  )
}
