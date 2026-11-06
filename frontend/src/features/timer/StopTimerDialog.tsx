import { formatClock } from '@/lib/duration'
import { CommentDialog } from './CommentDialog'
import type { TimeEntry } from './types'
import { useStopTimer } from './useTimer'

interface StopTimerDialogProps {
  entry: TimeEntry
  elapsedSeconds: number | null
  onClose: () => void
}

/** RF-05.16: no comment, no stop — the timer keeps running until this is sent. */
export function StopTimerDialog({ entry, elapsedSeconds, onClose }: StopTimerDialogProps) {
  const stop = useStopTimer()

  return (
    <CommentDialog
      title="Detener cronómetro"
      submitLabel="Detener"
      pending={stop.isPending}
      error={stop.error}
      onSubmit={(comment) => stop.mutate(comment, { onSuccess: onClose })}
      onClose={onClose}
    >
      <p>
        <span className="font-mono uppercase">{entry.task.code}</span> · {entry.task.title}
      </p>
      <p className="text-muted">
        {entry.project.name}
        {elapsedSeconds !== null && ` · ${formatClock(elapsedSeconds)}`}
      </p>
    </CommentDialog>
  )
}
