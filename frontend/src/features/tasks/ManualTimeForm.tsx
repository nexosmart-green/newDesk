import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { CommentField } from '@/features/timer/CommentField'
import { TimerBlockedNotice } from '@/features/timer/TimerBlockedNotice'
import { isValidComment } from '@/features/timer/types'
import { useLogManualTime } from '@/features/timer/useTimer'
import { ApiError, fieldErrorOf } from '@/lib/api'

/** Today as `YYYY-MM-DD` in the browser's calendar (the backend reads it in the person's timezone). */
function today(): string {
  const now = new Date()
  const pad = (value: number) => value.toString().padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

interface ManualTimeFormProps {
  taskCode: string
  projectId: number | undefined
}

/** RF-05.09 "olvidé el timer": date, from–to and a mandatory comment. Window and bag are backend rules. */
export function ManualTimeForm({ taskCode, projectId }: ManualTimeFormProps) {
  const log = useLogManualTime(taskCode)
  const [date, setDate] = useState(today)
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
