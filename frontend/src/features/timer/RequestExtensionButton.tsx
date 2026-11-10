import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { useRequestExtension } from '@/features/hours/useHours'

/** RF-05.08 / CA-05.05: one click from the block; whoever has `hours.allocate` gets notified. */
export function RequestExtensionButton({ projectId }: { projectId: number }) {
  const request = useRequestExtension()

  if (request.isSuccess) return <SuccessNotice message="Pedido de ampliación enviado." />

  return (
    <div className="space-y-2">
      <Button disabled={request.isPending} onClick={() => request.mutate({ projectId })}>
        {request.isPending ? 'Enviando…' : 'Pedir ampliación'}
      </Button>
      <ErrorAlert error={request.error} showFieldErrors />
    </div>
  )
}
