import { useId, useRef, useState } from 'react'
import { MarkdownView } from '@/components/ui/Markdown'
import { inputClassName } from '@/components/ui/TextField'
import { UploadProgressList } from '../uploads/UploadProgressList'
import { ImageFileInput } from './ImageFileInput'
import { MarkdownToolbar } from './MarkdownToolbar'
import { useMarkdownInput } from './useMarkdownInput'

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

/** Markdown (GFM) with a format bar, Escribir / Vista previa, and images by paste, drop or picker. */
export function MarkdownEditor({ label, value, onChange, projectId, error, rows = 8 }: MarkdownEditorProps) {
  const id = useId()
  const fileRef = useRef<HTMLInputElement>(null)
  const [mode, setMode] = useState<Mode>('write')
  const { textareaRef, handleShortcut, textareaHandlers, ...input } = useMarkdownInput({ value, onChange, projectId })
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
      <div className={`overflow-hidden rounded-lg border bg-bg transition-colors ${input.dragging ? 'border-accent' : error ? 'border-danger' : 'border-border'}`}>
        {mode === 'write' ? (
          <>
            <div className="border-b border-border bg-surface px-1.5 py-1">
              <MarkdownToolbar onCommand={input.runCommand} onPickImage={() => fileRef.current?.click()} canUpload={input.canUpload} />
            </div>
            <textarea
              ref={textareaRef}
              id={id}
              rows={rows}
              value={value}
              aria-invalid={error ? true : undefined}
              aria-describedby={messageId}
              onChange={(event) => onChange(event.target.value)}
              onKeyDown={handleShortcut}
              {...textareaHandlers}
              className={`${inputClassName} block resize-y rounded-none border-0 font-[inherit] leading-relaxed focus-visible:outline-0`}
            />
          </>
        ) : (
          <div className="min-h-40 px-3 py-3" aria-live="polite">
            {value.trim() ? <MarkdownView source={value} /> : <p className="text-sm text-muted">Nada para previsualizar todavía.</p>}
          </div>
        )}
      </div>
      <UploadProgressList uploads={input.uploads} label="Imágenes subiendo" />
      <p id={messageId} className={error ? 'text-sm text-danger' : 'text-xs text-muted'}>
        {error ?? (input.canUpload ? 'Admite Markdown. Pegá o arrastrá imágenes (JPG, PNG, WEBP o GIF, hasta 5 MB).' : 'Admite Markdown.')}
      </p>
      <ImageFileInput inputRef={fileRef} onFiles={input.addImages} />
    </div>
  )
}
