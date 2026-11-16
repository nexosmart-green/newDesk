import { Upload } from 'lucide-react'
import { useId, useRef, useState, type DragEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ATTACHMENT_ACCEPT } from './attachmentRules'

interface AttachmentDropzoneProps {
  onFiles: (files: File[]) => void
  /** The empty tab gives the zone more room: it is the whole call to action. */
  prominent?: boolean
}

/** Drop files here or pick them; the button is the keyboard path. */
export function AttachmentDropzone({ onFiles, prominent = false }: AttachmentDropzoneProps) {
  const hintId = useId()
  const inputRef = useRef<HTMLInputElement>(null)
  // dragenter/leave fire for every child: count them so the highlight doesn't flicker.
  const depth = useRef(0)
  const [over, setOver] = useState(false)

  const hasFiles = (event: DragEvent) => event.dataTransfer.types.includes('Files')

  function handleDrop(event: DragEvent) {
    event.preventDefault()
    depth.current = 0
    setOver(false)
    const files = Array.from(event.dataTransfer.files)
    if (files.length > 0) onFiles(files)
  }

  return (
    <div
      onDragEnter={(event) => {
        if (!hasFiles(event)) return
        depth.current++
        setOver(true)
      }}
      onDragOver={(event) => {
        if (hasFiles(event)) event.preventDefault()
      }}
      onDragLeave={() => {
        depth.current = Math.max(0, depth.current - 1)
        if (depth.current === 0) setOver(false)
      }}
      onDrop={handleDrop}
      className={`flex items-center gap-3 rounded-lg border-2 border-dashed transition-colors ${prominent ? 'flex-col px-6 py-10 text-center' : 'flex-row flex-wrap p-3 text-left sm:p-4'} ${over ? 'border-accent bg-accent/10' : 'border-border bg-surface'}`}
    >
      <span className={`shrink-0 place-items-center rounded-full bg-surface-2 ${prominent ? 'grid size-12' : 'hidden size-10 sm:grid'} ${over ? 'text-accent' : 'text-muted'}`}>
        <Upload aria-hidden="true" className="size-5" />
      </span>
      <div className="min-w-48 flex-1 space-y-0.5">
        <p className="font-medium">
          {over ? (
            'Soltá para adjuntar'
          ) : prominent ? (
            'Todavía no hay archivos'
          ) : (
            <>
              <span className="pointer-fine:hidden">Adjuntá archivos</span>
              <span className="hidden pointer-fine:inline">Arrastrá archivos acá</span>
            </>
          )}
        </p>
        <p id={hintId} className="text-sm text-muted">
          {prominent ? 'Arrastrá archivos acá o elegilos. ' : ''}Imágenes, PDF, Office, texto, CSV o ZIP, hasta 20 MB cada uno.
        </p>
      </div>
      <Button variant={prominent ? 'primary' : 'secondary'} onClick={() => inputRef.current?.click()} aria-describedby={hintId}>
        Elegir archivos
      </Button>
      <input
        ref={inputRef}
        type="file"
        multiple
        accept={ATTACHMENT_ACCEPT}
        hidden
        aria-hidden="true"
        tabIndex={-1}
        data-testid="attachment-input"
        onChange={(event) => {
          const files = Array.from(event.target.files ?? [])
          event.target.value = ''
          if (files.length > 0) onFiles(files)
        }}
      />
    </div>
  )
}
