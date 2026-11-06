import type { CSSProperties } from 'react'
import { formatPercent, levelOf, type Level } from '../lib/format'

// Grafito en nivel normal; el color aparece recién al pasar un umbral.
const levelColor: Record<Level, string> = { ok: 'var(--accent)', warn: 'var(--warn)', crit: 'var(--crit)' }

interface MeterProps {
  label: string
  percent: number | null | undefined
  detail?: string
  warn?: number
  crit?: number
}

export function Meter({ label, percent, detail, warn = 80, crit = 90 }: MeterProps) {
  const value = percent ?? null
  const level = value === null ? 'ok' : levelOf(value, warn, crit)
  const color = levelColor[level]
  const width = Math.min(100, Math.max(0, value ?? 0))

  return (
    <div className="min-w-0">
      <div className="mb-1.5 flex items-baseline justify-between gap-2">
        <span className="label">{label}</span>
        <span className="num text-footnote font-medium" style={level === 'ok' ? undefined : { color }}>
          {formatPercent(value)}
        </span>
      </div>
      <div
        role="meter"
        aria-label={label}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={value ?? undefined}
        className="h-1.5 overflow-hidden rounded-full bg-surface-3"
      >
        <div className="bar" style={{ '--v': `${width}%`, background: color } as CSSProperties} />
      </div>
      {detail && <div className="num mt-1.5 truncate text-xs text-faint">{detail}</div>}
    </div>
  )
}
