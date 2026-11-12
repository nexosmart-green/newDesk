import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { TextField } from '@/components/ui/TextField'
import { CommentField } from '@/features/timer/CommentField'
import { isValidComment, type CorrectEntryInput, type TimeEntry } from '@/features/timer/types'
import { useCorrectTimeEntry } from '@/features/timer/useTimer'
import { fieldErrorOf } from '@/lib/api'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { entryFormTimes } from './entryTimes'

interface EditEntryDialogProps {
  entry: TimeEntry & { ended_at: string }
  onClose: () => void
}

/** RF-05.15: the window and who may are backend rules; its message is shown as is. */
export function EditEntryDialog({ entry, onClose }: EditEntryDialogProps) {
  const correct = useCorrectTimeEntry()
  const timeZone = useProfileTimeZone()
  // Prefill in the profile timezone: the backend reads the submitted date/from/to in that same zone.
  const [initial] = useState(() => entryFormTimes(entry.started_at, entry.ended_at, timeZone))
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
    <Dialog title={`Corregir registro de ${entry.task.code}`} description={entry.task.title}
      size="md"
      dirty={timesChanged || comment !== (entry.comment ?? '')}
      onClose={onClose}
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField
          label="Fecha"
          type="date"
          required
          value={range.date}
          hint={`Fecha y horas en tu zona horaria (${timeZone}).`}
          onChange={(event) => setRange({ ...range, date: event.target.value })}
          error={fieldErrorOf(correct.error, 'date')}
        />
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr]">
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
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={correct.isPending} disabled={!isValidComment(comment)}>
            Corregir registro
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
