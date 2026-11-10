import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { PageState } from '@/components/ui/PageState'
import { formatDateTime } from '@/lib/dates'
import { mentionedIdsIn, type Mentionable } from './mentions'
import { MentionTextarea } from './MentionTextarea'
import { usePostTaskComment, useTaskComments } from './useTasks'

interface TaskCommentsProps {
  code: string
  members: Mentionable[]
}

/** RF-04.06: comments with @mentions (the backend notifies the mentioned people). */
export function TaskComments({ code, members }: TaskCommentsProps) {
  const comments = useTaskComments(code)
  const post = usePostTaskComment(code)
  const [body, setBody] = useState('')
  const [picked, setPicked] = useState<Mentionable[]>([])

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const text = body.trim()
    if (!text) return
    post.mutate(
      { body: text, mentioned_user_ids: mentionedIdsIn(text, picked) },
      {
        onSuccess: () => {
          setBody('')
          setPicked([])
        },
      },
    )
  }

  return (
    <section aria-labelledby="task-comments-title" className="space-y-3">
      <h2 id="task-comments-title" className="font-semibold">
        Comentarios
      </h2>
      {!comments.data ? (
        <PageState isPending={comments.isPending} error={comments.error} />
      ) : comments.data.data.length === 0 ? (
        <p className="text-sm text-muted">Todavía no hay comentarios.</p>
      ) : (
        <ul className="space-y-3">
          {comments.data.data.map((comment) => (
            <li key={comment.id} className="rounded border border-border bg-surface p-3">
              <p className="text-xs text-muted">
                <span className="font-medium text-text">{comment.author.full_name}</span>
                {comment.created_at && ` · ${formatDateTime(comment.created_at)}`}
              </p>
              <p className="whitespace-pre-line text-sm">{comment.body}</p>
            </li>
          ))}
        </ul>
      )}
      <form onSubmit={handleSubmit} className="space-y-2">
        <MentionTextarea
          label="Nuevo comentario"
          value={body}
          members={members}
          onChange={setBody}
          onMention={(member) => setPicked((current) => [...current, member])}
        />
        <ErrorAlert error={post.error} showFieldErrors />
        <div className="flex justify-end">
          <Button type="submit" disabled={post.isPending || !body.trim()}>
            {post.isPending ? 'Publicando…' : 'Comentar'}
          </Button>
        </div>
      </form>
    </section>
  )
}
