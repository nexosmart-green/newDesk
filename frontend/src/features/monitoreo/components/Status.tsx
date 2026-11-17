import type { ReactNode } from 'react'
import type { ServerStatus } from '../api/types'
import { serverStatusMeta, toneColor, type Tone } from '../lib/status'

// Punto + texto: el estado nunca se comunica solo con color.
export function Badge({ tone, children }: { tone: Tone; children: ReactNode }) {
  const color = toneColor(tone)
  return (
    <span
      className="inline-flex h-5 items-center gap-1.5 rounded-full px-2 text-xs font-medium whitespace-nowrap"
      style={{ color, background: `color-mix(in oklab, ${color} 12%, transparent)` }}
    >
      <span className="size-1.5 shrink-0 rounded-full" style={{ background: color }} />
      {children}
    </span>
  )
}

export function ServerStatusBadge({ status }: { status: ServerStatus }) {
  const meta = serverStatusMeta[status]
  return <Badge tone={meta.tone}>{meta.label}</Badge>
}

export function YesNo({ value, yes = 'Sí', no = 'No', invert = false }: { value: boolean; yes?: string; no?: string; invert?: boolean }) {
  const good = invert ? !value : value
  return <Badge tone={good ? 'ok' : 'crit'}>{value ? yes : no}</Badge>
}
