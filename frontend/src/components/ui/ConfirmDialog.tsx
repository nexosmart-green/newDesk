import type { ReactNode } from 'react'
import { Button } from './Button'
import { Dialog, DialogFooter } from './Dialog'
import { FieldErrorsAlert } from './ErrorAlert'

interface ConfirmDialogProps {
  title: string
  children: ReactNode
  /** The real verb + object ("Borrar", "Finalizar proyecto"), never "Confirmar" or "Aceptar". */
  confirmLabel: string
  /** "Cancelar" unless the action itself is a cancel ("Cancelar proyecto" → "Volver"). */
  cancelLabel?: string
  pending: boolean
  error?: unknown
  danger?: boolean
  onConfirm: () => void
  onClose: () => void
}

export function ConfirmDialog({ title, children, confirmLabel, cancelLabel = 'Cancelar', pending, error, danger, onConfirm, onClose }: ConfirmDialogProps) {
  return (
    <Dialog title={title} size="sm" onClose={onClose}>
      <div className="space-y-2 text-sm leading-relaxed">{children}</div>
      <FieldErrorsAlert error={error} />
      <DialogFooter>
        {/* Destructive confirmations start on Cancel so Enter never deletes by accident. */}
        <Button variant="secondary" onClick={onClose} data-autofocus={danger ? true : undefined}>
          {cancelLabel}
        </Button>
        <Button variant={danger ? 'danger' : 'primary'} loading={pending} onClick={onConfirm} data-autofocus={danger ? undefined : true}>
          {confirmLabel}
        </Button>
      </DialogFooter>
    </Dialog>
  )
}
