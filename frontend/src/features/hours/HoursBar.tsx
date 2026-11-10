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
    return (
      <p className="text-sm text-muted">
        {compact ? 'Interno' : `Interno: sin tope · Consumidas ${formatDuration(bag.consumed_seconds)}`}
      </p>
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
      className={`w-full overflow-hidden rounded-full bg-border ${compact ? 'h-1.5' : 'h-3'}`}
    >
      <div className={`h-full ${fillClass[level]}`} style={{ width: `${Math.min(percent, 100)}%` }} />
    </div>
  )

  if (compact) {
    return (
      <div className="w-full min-w-24 space-y-1 sm:w-28">
        <span className={`text-xs font-medium ${textClass[level]}`}>{percent}% consumido</span>
        {meter}
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <span className={`text-sm font-medium ${textClass[level]}`}>{levelLabel(bag, level)}</span>
        <span className="text-sm text-muted">{percent}% consumido</span>
      </div>
      {meter}
      <dl className="grid grid-cols-3 gap-2 text-sm">
        <div>
          <dt className="text-xs text-muted">Bolsa</dt>
          <dd>{formatDuration(bag.allocated_seconds ?? 0)}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted">Consumidas</dt>
          <dd>{formatDuration(bag.consumed_seconds)}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted">Disponibles</dt>
          <dd className={(bag.available_seconds ?? 0) <= 0 ? 'text-danger' : undefined}>
            {(bag.available_seconds ?? 0) < 0 ? '−' : ''}
            {formatDuration(Math.abs(bag.available_seconds ?? 0))}
          </dd>
        </div>
      </dl>
    </div>
  )
}
