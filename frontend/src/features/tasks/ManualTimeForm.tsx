import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { CommentField } from '@/features/timer/CommentField'
import { TimerBlockedNotice } from '@/features/timer/TimerBlockedNotice'
import { isValidComment } from '@/features/timer/types'
import { useLogManualTime } from '@/features/timer/useTimer'
import { ApiError, fieldErrorOf } from '@/lib/api'
import { todayIn } from '@/lib/timezone'

interface ManualTimeFormProps {
  taskCode: string
  projectId: number | undefined
}

/** RF-05.09 "olvidé el timer": date, from–to and a mandatory comment. Window and bag are backend rules. */
export function ManualTimeForm({ taskCode, projectId }: ManualTimeFormProps) {
  const log = useLogManualTime(taskCode)
  const timeZone = useProfileTimeZone()
  // The backend reads date + from/to in the profile timezone, so "today" is today there.
  const [date, setDate] = useState(() => todayIn(timeZone))
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [comment, setComment] = useState('')
  const ready = from !== '' && to !== '' && isValidComment(comment)
  const ruleError = log.error instanceof ApiError && log.error.code ? log.error : null

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!ready) return
    log.mutate({ date, from, to, comment: comment.trim() }, { onSuccess: () => setComment('') })
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3" aria-label="Cargar horas">
      <p className="text-xs text-muted">Fecha y horas en tu zona horaria ({timeZone}).</p>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <TextField
          label="Fecha"
          type="date"
          required
          className="col-span-2 sm:col-span-1"
          value={date}
          onChange={(event) => setDate(event.target.value)}
          error={fieldErrorOf(log.error, 'date')}
        />
        <TextField label="Desde" type="time" required value={from} onChange={(event) => setFrom(event.target.value)} error={fieldErrorOf(log.error, 'from')} />
        <TextField label="Hasta" type="time" required value={to} onChange={(event) => setTo(event.target.value)} error={fieldErrorOf(log.error, 'to')} />
      </div>
      <CommentField value={comment} onChange={setComment} error={fieldErrorOf(log.error, 'comment')} />
      {ruleError ? (
        <TimerBlockedNotice code={ruleError.code} message={ruleError.message} projectId={projectId} />
      ) : (
        <ErrorAlert error={log.error} />
      )}
      {log.isSuccess && <SuccessNotice message="Horas cargadas." />}
      <div className="flex justify-end">
        <Button type="submit" disabled={log.isPending || !ready}>
          {log.isPending ? 'Cargando…' : 'Cargar horas'}
        </Button>
      </div>
    </form>
  )
}
