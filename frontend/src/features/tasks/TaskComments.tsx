import { MessageSquare } from 'lucide-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { MarkdownView } from '@/components/ui/Markdown'
import { PageState } from '@/components/ui/PageState'
import { formatDateTime } from '@/lib/dates'
import { CommentComposer } from './comments/CommentComposer'
import type { TaskComment } from './types'
import { useTaskComments } from './useTasks'

interface TaskCommentsProps {
  code: string
  /** Pasted images are stored in the task's project. */
  projectId: number | undefined
}

function CommentItem({ comment }: { comment: TaskComment }) {
  return (
    <li className="space-y-1.5 rounded-lg border border-border bg-surface p-3">
      <p className="text-xs text-muted">
        <span className="font-medium text-text">{comment.author.full_name}</span>
        {comment.created_at && (
          <>
            {' · '}
            <time dateTime={comment.created_at}>{formatDateTime(comment.created_at)}</time>
          </>
        )}
      </p>
      <MarkdownView source={comment.body} breaks highlights={comment.mentions.map((person) => `@${person.full_name}`)} className="text-sm" />
    </li>
  )
}

/** RF-04.06: comments in markdown with @mentions (the backend notifies the mentioned people). */
export function TaskComments({ code, projectId }: TaskCommentsProps) {
  const comments = useTaskComments(code)

  return (
    <section aria-labelledby="task-comments-title" className="space-y-3">
      <h2 id="task-comments-title" className="font-semibold">
        Comentarios
      </h2>
      {!comments.data ? (
        <PageState isPending={comments.isPending} error={comments.error} shape="list" onRetry={() => void comments.refetch()} />
      ) : comments.data.data.length === 0 ? (
        <EmptyState icon={MessageSquare} bare>
          Todavía no hay comentarios. Escribí el primero abajo; con @ avisás a alguien del proyecto.
        </EmptyState>
      ) : (
        <ul className="space-y-3" aria-label="Comentarios de la tarea">
          {comments.data.data.map((comment) => (
            <CommentItem key={comment.id} comment={comment} />
          ))}
        </ul>
      )}
      <CommentComposer code={code} projectId={projectId} />
    </section>
  )
}
