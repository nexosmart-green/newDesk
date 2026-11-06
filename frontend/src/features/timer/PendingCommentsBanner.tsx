import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { PendingCommentDialog } from './PendingCommentDialog'
import { toPendingSummary } from './pendingEntry'
import { useTimerState } from './useTimer'

function bannerText(count: number): string {
  return count === 1 ? 'Tenés 1 registro sin comentario' : `Tenés ${count} registros sin comentario`
}

/** RF-05.17: persistent until every automatically cut entry has its comment (no new timer before that). */
export function PendingCommentsBanner() {
  const timer = useTimerState()
  const [completing, setCompleting] = useState(false)
  const pending = timer.data?.pending_comments ?? []
  const current = pending[0]

  if (!current) return null

  return (
    <div
      role="status"
      className="flex flex-wrap items-center justify-between gap-2 border-b border-warning/40 bg-warning/10 px-4 py-2 text-sm"
    >
      <p>
        <strong>{bannerText(pending.length)}.</strong> Completalo para poder iniciar otro cronómetro.
      </p>
      <Button variant="secondary" onClick={() => setCompleting(true)}>
        Completar
      </Button>
      {completing && (
        <PendingCommentDialog
          entry={toPendingSummary(current)}
          position={pending.length > 1 ? `Registro 1 de ${pending.length}` : undefined}
          onDone={() => {
            if (pending.length <= 1) setCompleting(false)
          }}
          onClose={() => setCompleting(false)}
        />
      )}
    </div>
  )
}
