import { useId, useLayoutEffect, useRef, useState, type ClipboardEvent, type DragEvent, type KeyboardEvent } from 'react'
import { MarkdownView } from '@/components/ui/Markdown'
import { inputClassName } from '@/components/ui/TextField'
import { applyCommand, imageMarkdown, insertText, uploadMarker, type EditorState, type MarkdownCommand } from './markdownCommands'
import { MarkdownToolbar } from './MarkdownToolbar'
import { UploadProgressList } from './UploadProgressList'
import { ACCEPT_ATTRIBUTE, useImageUploads } from './useImageUploads'

interface MarkdownEditorProps {
  label: string
  value: string
  onChange: (value: string) => void
  /** Images are stored per project; without one the image button is off. */
  projectId: number | undefined
  error?: string
  rows?: number
}

type Mode = 'write' | 'preview'

const shortcuts: Record<string, MarkdownCommand> = { b: 'bold', i: 'italic', k: 'link' }

/** Markdown (GFM) with a format bar, Escribir / Vista previa, and images by paste, drop or picker. */
export function MarkdownEditor({ label, value, onChange, projectId, error, rows = 8 }: MarkdownEditorProps) {
  const id = useId()
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  // Uploads finish after more typing: they edit the latest text, not the one they started with.
  const valueRef = useRef(value)
  // A command's selection is applied right after the new text renders (setting the value resets it).
  const pendingSelection = useRef<{ start: number; end: number } | null>(null)
  useLayoutEffect(() => {
    valueRef.current = value
    const selection = pendingSelection.current
    if (selection && textareaRef.current) {
      pendingSelection.current = null
      textareaRef.current.focus()
      textareaRef.current.setSelectionRange(selection.start, selection.end)
    }
  }, [value])
  const [mode, setMode] = useState<Mode>('write')
  const [dragging, setDragging] = useState(false)
  const images = useImageUploads(projectId)

  function currentState(): EditorState {
    const textarea = textareaRef.current
    return { value: valueRef.current, start: textarea?.selectionStart ?? value.length, end: textarea?.selectionEnd ?? value.length }
  }

  function commit(next: EditorState) {
    valueRef.current = next.value
    pendingSelection.current = { start: next.start, end: next.end }
    onChange(next.value)
  }

  function addImages(files: File[]) {
    for (const file of files) {
      const uploadId = images.nextId()
      const marker = uploadMarker(uploadId)
      commit(insertText(currentState(), `${marker}\n`))
      void images.upload(file, uploadId).then((url) => {
        const swapped = url ? imageMarkdown(url, file.name) : ''
        // Function replacement: a file name containing `$&` or `$'` must not be expanded as a pattern.
        const next = valueRef.current.replace(url ? marker : `${marker}\n`, () => swapped)
        valueRef.current = next
        onChange(next)
      })
    }
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    const command = (event.ctrlKey || event.metaKey) && shortcuts[event.key.toLowerCase()]
    if (!command) return
    event.preventDefault()
    commit(applyCommand(currentState(), command))
  }

  function handlePaste(event: ClipboardEvent<HTMLTextAreaElement>) {
    // Excel, Word and browsers put a bitmap next to the copied text: when there is text, paste the text.
    const { types } = event.clipboardData
    if (types.includes('text/plain') || types.includes('text/html')) return
    const files = Array.from(event.clipboardData.files)
    if (files.length === 0 || !images.canUpload) return
    event.preventDefault()
    addImages(files)
  }

  function handleDrop(event: DragEvent<HTMLTextAreaElement>) {
    setDragging(false)
    const files = Array.from(event.dataTransfer.files)
    if (files.length === 0 || !images.canUpload) return
    event.preventDefault()
    addImages(files)
  }

  const messageId = `${id}-message`

  return (
    <div className="space-y-1">
      <div className="flex flex-wrap items-end justify-between gap-2">
        <label htmlFor={id} className="block text-sm text-muted">
          {label}
        </label>
        <div role="group" aria-label={`Modo de ${label.toLowerCase()}`} className="inline-flex rounded-lg border border-border p-0.5 text-sm">
          {(['write', 'preview'] as const).map((option) => (
            <button
              key={option}
              type="button"
              aria-pressed={mode === option}
              onClick={() => setMode(option)}
              className={`min-h-8 cursor-pointer rounded-md px-3 transition-colors focus-visible:outline-2 focus-visible:outline-accent ${mode === option ? 'bg-surface-2 font-medium text-text' : 'text-muted hover:text-text'}`}
            >
              {option === 'write' ? 'Escribir' : 'Vista previa'}
            </button>
          ))}
        </div>
      </div>
      <div className={`overflow-hidden rounded-lg border bg-bg transition-colors ${dragging ? 'border-accent' : error ? 'border-danger' : 'border-border'}`}>
        {mode === 'write' ? (
          <>
            <div className="border-b border-border bg-surface px-1.5 py-1">
              <MarkdownToolbar
                onCommand={(command) => commit(applyCommand(currentState(), command))}
                onPickImage={() => fileRef.current?.click()}
                canUpload={images.canUpload}
              />
            </div>
            <textarea
              ref={textareaRef}
              id={id}
              rows={rows}
              value={value}
              aria-invalid={error ? true : undefined}
              aria-describedby={messageId}
              onChange={(event) => onChange(event.target.value)}
              onKeyDown={handleKeyDown}
              onPaste={handlePaste}
              onDragOver={(event) => {
                if (event.dataTransfer.types.includes('Files')) setDragging(true)
              }}
              onDragLeave={() => setDragging(false)}
              onDrop={handleDrop}
              className={`${inputClassName} block resize-y rounded-none border-0 font-[inherit] leading-relaxed focus-visible:outline-0`}
            />
          </>
        ) : (
          <div className="min-h-40 px-3 py-3" aria-live="polite">
            {value.trim() ? <MarkdownView source={value} /> : <p className="text-sm text-muted">Nada para previsualizar todavía.</p>}
          </div>
        )}
      </div>
      <UploadProgressList uploads={images.uploads} />
      <p id={messageId} className={error ? 'text-sm text-danger' : 'text-xs text-muted'}>
        {error ?? (images.canUpload ? 'Admite Markdown. Pegá o arrastrá imágenes (JPG, PNG, WEBP o GIF, hasta 5 MB).' : 'Admite Markdown.')}
      </p>
      <input
        ref={fileRef}
        type="file"
        accept={ACCEPT_ATTRIBUTE}
        multiple
        hidden
        aria-hidden="true"
        tabIndex={-1}
        onChange={(event) => {
          addImages(Array.from(event.target.files ?? []))
          event.target.value = ''
        }}
      />
    </div>
  )
}
