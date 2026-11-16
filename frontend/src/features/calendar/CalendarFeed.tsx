import { CalendarPlus } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { CopyableValue } from '@/components/ui/CopyButton'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { useCalendarFeedStatus, useIssueCalendarFeed, useRevokeCalendarFeed } from './useCalendar'

/**
 * RF-06.07 iCal feed. The URL only exists in the response that issued it: it is shown once and
 * dropped when hidden. Generating another one rotates the token (the old URL stops working).
 *
 * The buttons live in the page header (`actions`); what they produce — the URL, an error, the revoke
 * confirmation — shows in the page (`result`).
 */
export function useCalendarFeed(): { actions: ReactNode; result: ReactNode } {
  const status = useCalendarFeedStatus()
  const issue = useIssueCalendarFeed()
  const revoke = useRevokeCalendarFeed()
  const [confirmingRevoke, setConfirmingRevoke] = useState(false)

  function closeRevoke() {
    setConfirmingRevoke(false)
  }

  const actions = (
    <>
      {status.data === true && (
        <Button variant="ghost" onClick={() => setConfirmingRevoke(true)}>
          Revocar suscripción
        </Button>
      )}
      <Button
        variant="secondary"
        onClick={() => {
          revoke.reset()
          issue.mutate()
        }}
        loading={issue.isPending}
      >
        <CalendarPlus aria-hidden="true" className="size-4" />
        Suscribirme (iCal)
      </Button>
    </>
  )

  const result = (
    <>
      <FieldErrorsAlert error={issue.error} />
      {issue.data && (
        <section aria-label="Suscripción iCal" className="space-y-2 rounded-lg border border-line bg-surface p-4 text-sm">
          <p>Pegá esta URL en Google Calendar («Desde URL») o en el calendario del celular:</p>
          <CopyableValue value={issue.data} label="Copiar URL del calendario" />
          <p className="text-xs text-muted">
            Se muestra una sola vez. Si generás otra, esta deja de funcionar. Es personal: no la compartas.
          </p>
          <Button variant="link" onClick={() => issue.reset()}>
            Ocultar
          </Button>
        </section>
      )}
      {confirmingRevoke && (
        <ConfirmDialog
          title="¿Revocar la suscripción?"
          confirmLabel="Revocar suscripción"
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
          <p>Tu URL del calendario deja de funcionar: Google Calendar o el celular dejan de recibir tus turnos. Para volver a verlos, suscribite de nuevo.</p>
        </ConfirmDialog>
      )}
    </>
  )

  return { actions, result }
}
