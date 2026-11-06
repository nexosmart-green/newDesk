import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { CommentField } from '@/features/timer/CommentField'
import { isValidComment, type CorrectEntryInput, type TimeEntry } from '@/features/timer/types'
import { useCorrectTimeEntry } from '@/features/timer/useTimer'
import { fieldErrorOf } from '@/lib/api'
import { localDateOf, localTimeOf } from './entryTimes'

interface EditEntryDialogProps {
  entry: TimeEntry & { ended_at: string }
  onClose: () => void
}

/** RF-05.15: the window and who may are backend rules; its message is shown as is. */
export function EditEntryDialog({ entry, onClose }: EditEntryDialogProps) {
  const correct = useCorrectTimeEntry()
  const initial = { date: localDateOf(entry.started_at), from: localTimeOf(entry.started_at), to: localTimeOf(entry.ended_at) }
  const [range, setRange] = useState(initial)
  const [comment, setComment] = useState(entry.comment ?? '')
  const timesChanged = range.date !== initial.date || range.from !== initial.from || range.to !== initial.to

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!isValidComment(comment)) return
    // Only send times when they changed: a comment-only fix keeps the stored instants untouched.
    const input: CorrectEntryInput = timesChanged ? { ...range, comment: comment.trim() } : { comment: comment.trim() }
    correct.mutate({ entryId: entry.id, input }, { onSuccess: onClose })
  }

  return (
    <Dialog title={`Corregir registro de ${entry.task.code}`} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField
          label="Fecha"
          type="date"
          required
          value={range.date}
          onChange={(event) => setRange({ ...range, date: event.target.value })}
          error={fieldErrorOf(correct.error, 'date')}
        />
        <div className="grid grid-cols-2 gap-3">
          <TextField
            label="Desde"
            type="time"
            required
            value={range.from}
            onChange={(event) => setRange({ ...range, from: event.target.value })}
            error={fieldErrorOf(correct.error, 'from')}
          />
          <TextField
            label="Hasta"
            type="time"
            required
            value={range.to}
            onChange={(event) => setRange({ ...range, to: event.target.value })}
            error={fieldErrorOf(correct.error, 'to')}
          />
        </div>
        <CommentField label="Comentario" value={comment} onChange={setComment} error={fieldErrorOf(correct.error, 'comment')} />
        <ErrorAlert error={correct.error} />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={correct.isPending || !isValidComment(comment)}>
            {correct.isPending ? 'Guardando…' : 'Guardar'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
