import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { formatDateTime } from '@/lib/dates'
import { AllocationFormDialog } from './AllocationFormDialog'
import type { ExtensionRequest } from './types'
import { useApproveExtension, useExtensionRequests, useRejectExtension } from './useHours'

type Resolution = { request: ExtensionRequest; action: 'approve' | 'reject' }

/** RF-05.08: pending "pedir ampliación" for this project; approving loads the hours. */
export function ExtensionRequestsPanel({ projectId }: { projectId: number }) {
  const requests = useExtensionRequests(true)
  const approve = useApproveExtension()
  const reject = useRejectExtension()
  const [resolving, setResolving] = useState<Resolution | null>(null)
  const pending = (requests.data ?? []).filter((request) => request.project.id === projectId && request.status === 'pending')

  if (pending.length === 0) return null

  const close = () => {
    approve.reset()
    reject.reset()
    setResolving(null)
  }

  return (
    <section className="space-y-2 rounded-lg border border-warning/40 bg-warning/10 p-4" aria-labelledby="extension-requests">
      <h2 id="extension-requests" className="font-semibold">
        Pedidos de ampliación
      </h2>
      <ul className="space-y-3">
        {pending.map((request) => (
          <li key={request.id} className="flex flex-wrap items-start justify-between gap-2">
            <div className="text-sm">
              <p className="font-medium">{request.requested_by.full_name}</p>
              <p className="text-muted">{request.message ?? 'No dejó un motivo.'}</p>
              {request.created_at && <p className="text-xs text-muted">{formatDateTime(request.created_at)}</p>}
            </div>
            <div className="flex gap-2">
              <Button onClick={() => setResolving({ request, action: 'approve' })}>Aprobar</Button>
              <Button variant="secondary" onClick={() => setResolving({ request, action: 'reject' })}>
                Rechazar
              </Button>
            </div>
          </li>
        ))}
      </ul>
      {resolving?.action === 'approve' && (
        <AllocationFormDialog
          title="Aprobar ampliación"
          submitLabel="Aprobar y cargar horas"
          pending={approve.isPending}
          error={approve.error}
          onSubmit={(input) => approve.mutate({ requestId: resolving.request.id, input }, { onSuccess: close })}
          onClose={close}
        >
          <p className="text-sm text-muted">Pedido de {resolving.request.requested_by.full_name}.</p>
        </AllocationFormDialog>
      )}
      {resolving?.action === 'reject' && (
        <ConfirmDialog
          title={`¿Rechazar el pedido de ${resolving.request.requested_by.full_name}?`}
          confirmLabel="Rechazar pedido"
          danger
          pending={reject.isPending}
          error={reject.error}
          onConfirm={() => reject.mutate(resolving.request.id, { onSuccess: close })}
          onClose={close}
        >
          <p>No se suman horas al proyecto. Si hacen falta más, hay que pedir otra ampliación.</p>
        </ConfirmDialog>
      )}
    </section>
  )
}
