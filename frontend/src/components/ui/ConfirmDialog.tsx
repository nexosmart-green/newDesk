import type { ReactNode } from 'react'
import { Button } from './Button'
import { Dialog } from './Dialog'
import { ErrorAlert } from './ErrorAlert'

interface ConfirmDialogProps {
  title: string
  children: ReactNode
  confirmLabel: string
  pending: boolean
  error?: unknown
  danger?: boolean
  onConfirm: () => void
  onClose: () => void
}

export function ConfirmDialog({ title, children, confirmLabel, pending, error, danger, onConfirm, onClose }: ConfirmDialogProps) {
  return (
    <Dialog title={title} onClose={onClose}>
      <div className="space-y-2 text-sm">{children}</div>
      <ErrorAlert error={error} showFieldErrors />
      <div className="flex justify-end gap-2">
        <Button variant="secondary" onClick={onClose}>
          Cancelar
        </Button>
        <Button variant={danger ? 'danger' : 'primary'} disabled={pending} onClick={onConfirm}>
          {pending ? 'Procesando…' : confirmLabel}
        </Button>
      </div>
    </Dialog>
  )
}
