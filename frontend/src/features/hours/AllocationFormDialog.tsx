import { useState, type FormEvent, type ReactNode } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { hoursToSeconds } from './durationInput'
import { HoursInput } from './HoursInput'
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

function hoursProblem(seconds: number | null, typed: string, allowSubtract: boolean): string | undefined {
  if (typed.trim() === '') return undefined
  if (seconds === null) return 'Escribí un número de horas, por ejemplo 2,5.'
  if (seconds === 0) return 'Tiene que ser distinto de cero.'
  if (seconds < 0 && !allowSubtract) return 'Acá solo se suman horas: usá un número positivo.'
  return undefined
}

/** RF-05.04: hours (decimals) + mandatory reason + optional reference, sent as integer seconds (R-5). */
export function AllocationFormDialog(props: AllocationFormDialogProps) {
  const { title, submitLabel, allowSubtract = false, pending, error, children, onSubmit, onClose } = props
  const [hours, setHours] = useState('')
  const [reason, setReason] = useState('')
  const [reference, setReference] = useState('')
  const seconds = hoursToSeconds(hours)
  const problem = hoursProblem(seconds, hours, allowSubtract)
  const canSubmit = seconds !== null && seconds !== 0 && !problem && reason.trim() !== ''

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!canSubmit || seconds === null) return
    onSubmit({ seconds, reason: reason.trim(), reference: reference.trim() === '' ? null : reference.trim() })
  }

  return (
    <Dialog title={title} size="md" dirty={[hours, reason, reference].some((value) => value.trim() !== '')} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {children}
        <HoursInput value={hours} onChange={setHours} allowNegative={allowSubtract} error={problem ?? fieldErrorOf(error, 'seconds')} />
        <TextField
          label="Motivo"
          required
          maxLength={255}
          placeholder="Ej.: Presupuesto aprobado de noviembre"
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
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={pending} disabled={!canSubmit}>
            {submitLabel}
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
