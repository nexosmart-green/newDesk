import { useLayoutEffect, useRef, useState, type ClipboardEvent, type DragEvent, type KeyboardEvent } from 'react'
import { applyCommand, imageMarkdown, insertText, uploadMarker, type EditorState, type MarkdownCommand } from './markdownCommands'
import { useImageUploads } from './useImageUploads'

const shortcuts: Record<string, MarkdownCommand> = { b: 'bold', i: 'italic', k: 'link' }

interface MarkdownInputOptions {
  value: string
  onChange: (value: string) => void
  /** Images are stored per project; without one, images can't be added. */
  projectId: number | undefined
}

/**
 * What any markdown textarea needs (description editor, comment box): format commands and Ctrl+B/I/K,
 * and images by paste, drop or picker, uploaded with progress and swapped in where the cursor was.
 */
export function useMarkdownInput({ value, onChange, projectId }: MarkdownInputOptions) {
  const textareaRef = useRef<HTMLTextAreaElement>(null)
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
  const [dragging, setDragging] = useState(false)
  const images = useImageUploads(projectId)

  function currentState(): EditorState {
    const textarea = textareaRef.current
    const length = valueRef.current.length
    return { value: valueRef.current, start: textarea?.selectionStart ?? length, end: textarea?.selectionEnd ?? length }
  }

  function commit(next: EditorState) {
    valueRef.current = next.value
    pendingSelection.current = { start: next.start, end: next.end }
    onChange(next.value)
  }

  function addImages(files: File[]) {
    if (!images.canUpload) return
    for (const file of files) {
      const job = images.start(file)
      if (!job) continue
      const marker = uploadMarker(job.id)
      commit(insertText(currentState(), `${marker}\n`))
      void job.done.then((url) => {
        const swapped = url ? imageMarkdown(url, file.name) : ''
        // Function replacement: a file name containing `$&` or `$'` must not be expanded as a pattern.
        const next = valueRef.current.replace(url ? marker : `${marker}\n`, () => swapped)
        valueRef.current = next
        onChange(next)
      })
    }
  }

  function runCommand(command: MarkdownCommand) {
    commit(applyCommand(currentState(), command))
  }

  /** Ctrl/⌘+B, I, K. Returns whether it handled the key (so a caller's own handler can skip it). */
  function handleShortcut(event: KeyboardEvent<HTMLTextAreaElement>): boolean {
    const command = (event.ctrlKey || event.metaKey) && !event.altKey && shortcuts[event.key.toLowerCase()]
    if (!command) return false
    event.preventDefault()
    runCommand(command)
    return true
  }

  const textareaHandlers = {
    onPaste(event: ClipboardEvent<HTMLTextAreaElement>) {
      // Excel, Word and browsers put a bitmap next to the copied text: when there is text, paste the text.
      const { types } = event.clipboardData
      if (types.includes('text/plain') || types.includes('text/html')) return
      const files = Array.from(event.clipboardData.files)
      if (files.length === 0 || !images.canUpload) return
      event.preventDefault()
      addImages(files)
    },
    onDragOver(event: DragEvent<HTMLTextAreaElement>) {
      if (event.dataTransfer.types.includes('Files')) setDragging(true)
    },
    onDragLeave() {
      setDragging(false)
    },
    onDrop(event: DragEvent<HTMLTextAreaElement>) {
      setDragging(false)
      const files = Array.from(event.dataTransfer.files)
      if (files.length === 0 || !images.canUpload) return
      event.preventDefault()
      addImages(files)
    },
  }

  return { textareaRef, dragging, uploads: images.uploads, canUpload: images.canUpload, addImages, runCommand, handleShortcut, textareaHandlers }
}
