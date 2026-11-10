import { formatDateTime } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import type { HourAllocation } from './types'

/** Every bag movement, newest first. Never edited: corrections are new lines (RF-05 "Conceptos"). */
export function AllocationHistory({ allocations }: { allocations: HourAllocation[] }) {
  if (allocations.length === 0) return <p className="text-sm text-muted">Todavía no se cargaron horas.</p>

  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Historial de la bolsa">
      {allocations.map((allocation) => (
        <li key={allocation.id} className="grid gap-1 p-3 sm:grid-cols-[6rem_1fr_auto] sm:items-center sm:gap-3">
          <span className={`font-mono text-sm font-medium ${allocation.seconds < 0 ? 'text-danger' : 'text-success'}`}>
            {allocation.seconds < 0 ? '−' : '+'}
            {formatDuration(Math.abs(allocation.seconds))}
          </span>
          <span>
            <span className="block text-sm">{allocation.reason}</span>
            {allocation.reference && <span className="block text-xs text-muted">Ref.: {allocation.reference}</span>}
          </span>
          <span className="text-xs text-muted">
            {allocation.created_by} · {formatDateTime(allocation.created_at)}
          </span>
        </li>
      ))}
    </ul>
  )
}
