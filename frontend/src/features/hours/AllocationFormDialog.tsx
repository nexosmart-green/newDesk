import { useState, type FormEvent, type ReactNode } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { DurationInputs } from './DurationInputs'
import { emptyDuration, toSeconds } from './durationInput'
import type { AllocationInput } from './types'

interface AllocationFormDialogProps {
  title: string
  submitLabel: string
  /** Manual allocations can also take hours out (a correction is another line, never an edit). */
  allowSubtract?: boolean
  pending: boolean
  error: unknown
  children?: ReactNode
  onSubmit: (input: AllocationInput) => void
  onClose: () => void
}

/** RF-05.04: hours + mandatory reason + optional reference, sent as integer seconds (R-5). */
export function AllocationFormDialog(props: AllocationFormDialogProps) {
  const { title, submitLabel, allowSubtract = false, pending, error, children, onSubmit, onClose } = props
  const [duration, setDuration] = useState(emptyDuration)
  const [direction, setDirection] = useState<'add' | 'subtract'>('add')
  const [reason, setReason] = useState('')
  const [reference, setReference] = useState('')
  const seconds = toSeconds(duration.hours, duration.minutes)
  const canSubmit = seconds > 0 && reason.trim() !== ''

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!canSubmit) return
    onSubmit({
      seconds: direction === 'subtract' ? -seconds : seconds,
      reason: reason.trim(),
      reference: reference.trim() === '' ? null : reference.trim(),
    })
  }

  return (
    <Dialog title={title} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {children}
        {allowSubtract && (
          <SelectField
            label="Movimiento"
            value={direction}
            onChange={(event) => setDirection(event.target.value === 'subtract' ? 'subtract' : 'add')}
          >
            <option value="add">Sumar horas</option>
            <option value="subtract">Restar horas</option>
          </SelectField>
        )}
        <DurationInputs value={duration} onChange={setDuration} error={fieldErrorOf(error, 'seconds')} />
        <TextField
          label="Motivo"
          required
          maxLength={255}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          error={fieldErrorOf(error, 'reason')}
        />
        <TextField
          label="Referencia (opcional)"
          maxLength={120}
          placeholder="Ej.: Presupuesto #123"
          value={reference}
          onChange={(event) => setReference(event.target.value)}
          error={fieldErrorOf(error, 'reference')}
        />
        <ErrorAlert error={error} />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={pending || !canSubmit}>
            {pending ? 'Guardando…' : submitLabel}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
