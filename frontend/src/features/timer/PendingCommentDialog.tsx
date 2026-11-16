import { TaskCode } from '@/features/tasks/TaskCode'
import { formatDateTime } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import { CommentDialog } from './CommentDialog'
import type { PendingEntrySummary } from './pendingEntry'
import { useCompleteEntryComment } from './useTimer'

interface PendingCommentDialogProps {
  entry: PendingEntrySummary
  /** e.g. "Registro 1 de 3" when several are waiting. */
  position?: string
  onDone: () => void
  onClose: () => void
}

/** RF-05.17: what was done in an automatically cut entry; it unblocks the next timer. */
export function PendingCommentDialog({ entry, position, onDone, onClose }: PendingCommentDialogProps) {
  const complete = useCompleteEntryComment()

  return (
    <CommentDialog
      key={entry.id}
      title="Completá el comentario"
      submitLabel="Guardar comentario"
      pending={complete.isPending}
      error={complete.error}
      onSubmit={(comment) => complete.mutate({ entryId: entry.id, comment }, { onSuccess: onDone })}
      onClose={onClose}
    >
      {position && <p className="text-xs text-muted">{position}</p>}
      <p>
        <TaskCode code={entry.taskCode} className="mr-1.5" />
        {entry.taskTitle}
      </p>
      <p className="flex flex-wrap gap-x-4 gap-y-1 text-muted">
        <span>{entry.project}</span>
        <span>{formatDateTime(entry.startedAt)}</span>
        <span className="tabular">{formatDuration(entry.seconds)}</span>
      </p>
      {entry.stopReasonLabel && <p className="text-muted">Se cortó: {entry.stopReasonLabel}</p>}
    </CommentDialog>
  )
}
