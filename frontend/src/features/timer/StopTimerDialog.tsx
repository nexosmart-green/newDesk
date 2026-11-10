import { formatClock } from '@/lib/duration'
import { CommentDialog } from './CommentDialog'
import type { TimeEntry } from './types'
import { useElapsedSeconds } from './useElapsedSeconds'
import { useStopTimer, useTimerState } from './useTimer'

interface StopTimerDialogProps {
  entry: TimeEntry
  onClose: () => void
}

/**
 * RF-05.16: no comment, no stop. Sending closes at once (optimistic stop); if the server refuses,
 * the timer comes back in the header with a toast saying why.
 */
export function StopTimerDialog({ entry, onClose }: StopTimerDialogProps) {
  const stop = useStopTimer()
  // Keeps ticking while the person writes: the figure they see is what will be recorded.
  const elapsedSeconds = useElapsedSeconds(entry.elapsed_seconds, useTimerState().dataUpdatedAt)

  return (
    <CommentDialog
      title="Detener cronómetro"
      submitLabel="Detener"
      pending={false}
      error={null}
      onSubmit={(comment) => {
        stop.mutate(comment)
        onClose()
      }}
      onClose={onClose}
    >
      <div className="flex items-start justify-between gap-4 rounded-xl bg-surface-2 px-4 py-3">
        <div className="min-w-0">
          <p className="font-medium">
            <span className="mr-2 font-mono text-xs uppercase text-muted">{entry.task.code}</span>
            {entry.task.title}
          </p>
          {entry.project.name && <p className="text-muted">{entry.project.name}</p>}
        </div>
        <p className="tabular shrink-0 text-lg font-semibold tracking-tight">{formatClock(elapsedSeconds ?? 0)}</p>
      </div>
    </CommentDialog>
  )
}
