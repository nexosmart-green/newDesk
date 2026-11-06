import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { CopyableValue } from '@/components/ui/CopyButton'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { useIssueCalendarFeed, useRevokeCalendarFeed } from './useCalendar'

/**
 * RF-06.07 iCal feed. The URL only exists in the response that issued it: it is shown once and
 * dropped when hidden. Generating another one rotates the token (the old URL stops working).
 */
export function CalendarFeedPanel() {
  const issue = useIssueCalendarFeed()
  const revoke = useRevokeCalendarFeed()
  const [confirmingRevoke, setConfirmingRevoke] = useState(false)

  function closeRevoke() {
    setConfirmingRevoke(false)
  }

  return (
    <section aria-label="Suscripción iCal" className="space-y-3 rounded-lg border border-border bg-surface p-3">
      <div className="flex flex-wrap items-center gap-2">
        <Button
          onClick={() => {
            revoke.reset()
            issue.mutate()
          }}
          disabled={issue.isPending}
        >
          {issue.isPending ? 'Generando…' : 'Suscribirme (iCal)'}
        </Button>
        <Button variant="secondary" onClick={() => setConfirmingRevoke(true)}>
          Revocar suscripción
        </Button>
      </div>
      <ErrorAlert error={issue.error} showFieldErrors />
      {revoke.isSuccess && <SuccessNotice message="Suscripción revocada: la URL anterior ya no funciona." />}
      {issue.data && (
        <div className="space-y-2 text-sm">
          <p>Pegá esta URL en Google Calendar («Desde URL») o en el calendario del celular:</p>
          <CopyableValue value={issue.data} label="Copiar URL del calendario" />
          <p className="text-xs text-muted">
            Se muestra una sola vez. Si generás otra, esta deja de funcionar. Es personal: no la compartas.
          </p>
          <Button variant="link" onClick={() => issue.reset()}>
            Ocultar
          </Button>
        </div>
      )}
      {confirmingRevoke && (
        <ConfirmDialog
          title="Revocar suscripción"
          confirmLabel="Revocar"
          danger
          pending={revoke.isPending}
          error={revoke.error}
          onConfirm={() =>
            revoke.mutate(undefined, {
              onSuccess: () => {
                issue.reset()
                closeRevoke()
              },
            })
          }
          onClose={closeRevoke}
        >
          <p>Los calendarios suscriptos a tu URL dejan de actualizarse.</p>
        </ConfirmDialog>
      )}
    </section>
  )
}
