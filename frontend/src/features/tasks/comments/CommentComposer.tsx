import { useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { inputClassName } from '@/components/ui/TextField'
import { ImageFileInput } from '../description/ImageFileInput'
import { MarkdownToolbar } from '../description/MarkdownToolbar'
import type { MarkdownCommand } from '../description/markdownCommands'
import { useMarkdownInput } from '../description/useMarkdownInput'
import { mentionedIdsIn, type Mentionable } from '../mentions'
import { MentionTextarea } from '../MentionTextarea'
import { UploadProgressList } from '../uploads/UploadProgressList'
import { usePostTaskComment } from '../useTasks'

const commentCommands: MarkdownCommand[] = ['bold', 'italic', 'list', 'code', 'link']

interface CommentComposerProps {
  code: string
  projectId: number | undefined
  members: Mentionable[]
}

/** Markdown with a short format bar, @mentions, pasted/dropped images; Ctrl+Enter publishes. */
export function CommentComposer({ code, projectId, members }: CommentComposerProps) {
  const id = useId()
  const fileRef = useRef<HTMLInputElement>(null)
  const post = usePostTaskComment(code)
  const [body, setBody] = useState('')
  const [picked, setPicked] = useState<Mentionable[]>([])
  const { textareaRef, textareaHandlers, ...input } = useMarkdownInput({ value: body, onChange: setBody, projectId })
  const uploading = input.uploads.length > 0
  const canSend = body.trim() !== '' && !uploading

  function send() {
    const text = body.trim()
    if (!canSend || post.isPending) return
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

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    send()
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault()
      send()
      return
    }
    input.handleShortcut(event)
  }

  const hintId = `${id}-hint`

  return (
    <form onSubmit={handleSubmit} className="space-y-2">
      <label htmlFor={id} className="block text-sm text-muted">
        Nuevo comentario
      </label>
      <div className={`rounded-lg border bg-bg transition-colors focus-within:border-accent ${input.dragging ? 'border-accent' : 'border-border'}`}>
        <div className="rounded-t-lg border-b border-border bg-surface px-1.5 py-1">
          <MarkdownToolbar
            commands={commentCommands}
            onCommand={input.runCommand}
            onPickImage={() => fileRef.current?.click()}
            canUpload={input.canUpload}
          />
        </div>
        <MentionTextarea
          id={id}
          textareaRef={textareaRef}
          value={body}
          members={members}
          onChange={setBody}
          onMention={(member) => setPicked((current) => [...current, member])}
          onKeyDown={handleKeyDown}
          aria-describedby={hintId}
          {...textareaHandlers}
          className={`${inputClassName} block resize-y rounded-t-none rounded-b-lg border-0 font-[inherit] leading-relaxed focus-visible:outline-0`}
        />
      </div>
      <UploadProgressList uploads={input.uploads} label="Imágenes subiendo" />
      <FieldErrorsAlert error={post.error} />
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p id={hintId} className="text-xs text-muted">
          {uploading ? 'Esperá a que terminen de subir las imágenes.' : 'Escribí @ para mencionar. Admite Markdown y podés pegar imágenes. Ctrl+Enter publica.'}
        </p>
        <Button type="submit" disabled={!canSend} loading={post.isPending} className="sm:shrink-0">
          Comentar
        </Button>
      </div>
      <ImageFileInput inputRef={fileRef} onFiles={input.addImages} />
    </form>
  )
}
