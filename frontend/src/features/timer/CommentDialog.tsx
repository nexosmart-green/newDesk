import { useState, type FormEvent, type ReactNode } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { fieldErrorOf } from '@/lib/api'
import { CommentField } from './CommentField'
import { isValidComment } from './types'

interface CommentDialogProps {
  title: string
  children?: ReactNode
  submitLabel: string
  pending: boolean
  error: unknown
  onSubmit: (comment: string) => void
  onClose: () => void
}

/**
 * Every "¿qué hiciste?" prompt (stop, switch, pending entries): the submit stays disabled until the
 * comment reaches the minimum the backend enforces (RF-05.16).
 */
export function CommentDialog({ title, children, submitLabel, pending, error, onSubmit, onClose }: CommentDialogProps) {
  const [comment, setComment] = useState('')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (isValidComment(comment)) onSubmit(comment.trim())
  }

  return (
    <Dialog title={title} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        {children && <div className="space-y-1 text-sm">{children}</div>}
        <CommentField value={comment} onChange={setComment} error={fieldErrorOf(error, 'comment')} />
        <ErrorAlert error={error} />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={pending || !isValidComment(comment)}>
            {pending ? 'Guardando…' : submitLabel}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
