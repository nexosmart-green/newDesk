import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { TextField } from '@/components/ui/TextField'
import { formatDate } from '@/lib/dates'
import { fieldErrorOf } from '@/lib/api'
import type { ShiftRecurrence } from './recurrenceTypes'
import { useEndShiftRecurrence } from './useRecurrences'

interface EndRecurrenceDialogProps {
  recurrence: ShiftRecurrence
  today: string
  onClose: () => void
}

/** RF-06.03 "Finalizar": pick the last day; generated days after it go away, the earlier ones stay. */
export function EndRecurrenceDialog({ recurrence, today, onClose }: EndRecurrenceDialogProps) {
  const end = useEndShiftRecurrence()
  const earliest = recurrence.starts_on > today ? recurrence.starts_on : today
  const [endsOn, setEndsOn] = useState(earliest)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    end.mutate({ id: recurrence.id, endsOn }, { onSuccess: onClose })
  }

  return (
    <Dialog title={`Finalizar turno fijo de ${recurrence.user.full_name}`} size="sm" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField
          label="Último día"
          type="date"
          required
          min={earliest}
          value={endsOn}
          onChange={(event) => setEndsOn(event.target.value)}
          error={fieldErrorOf(end.error, 'ends_on')}
        />
        <p className="text-sm leading-relaxed text-muted">
          Los días que generó después del {endsOn ? formatDate(endsOn) : 'último día'} se quitan del calendario. Los anteriores
          quedan como están, igual que los turnos cargados a mano.
        </p>
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={!endsOn} loading={end.isPending}>
            Finalizar
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
