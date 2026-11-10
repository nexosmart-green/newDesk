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
        <span className="font-mono uppercase">{entry.taskCode}</span> · {entry.taskTitle}
      </p>
      <p className="text-muted">
        {entry.project} · {formatDateTime(entry.startedAt)} · {formatDuration(entry.seconds)}
      </p>
      {entry.stopReasonLabel && <p className="text-muted">Se cortó: {entry.stopReasonLabel}</p>}
    </CommentDialog>
  )
}
