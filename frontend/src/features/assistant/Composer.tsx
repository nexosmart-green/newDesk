import { useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Button } from '@/components/ui/Button'

const MAX_LENGTH = 4000
const MAX_HEIGHT_PX = 176

interface ComposerProps {
  onSend: (text: string) => void
  sending: boolean
  /** An answer is still being written: one turn at a time. */
  busy: boolean
}

/** Enter sends, Shift+Enter breaks the line. Grows with the text up to a few lines. */
export function Composer({ onSend, sending, busy }: ComposerProps) {
  const [text, setText] = useState('')
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const hintId = useId()
  const blocked = busy || sending

  function send(event?: FormEvent) {
    event?.preventDefault()
    const value = text.trim()
    if (!value || blocked) return
    onSend(value)
    setText('')
    if (textareaRef.current) textareaRef.current.style.height = ''
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) send(event)
  }

  function grow(element: HTMLTextAreaElement) {
    element.style.height = 'auto'
    element.style.height = `${Math.min(element.scrollHeight, MAX_HEIGHT_PX)}px`
  }

  return (
    <form onSubmit={send} className="space-y-1.5">
      <div className="flex items-end gap-2 rounded-[10px] border border-field-border bg-field p-2 focus-within:border-brand">
        <label htmlFor={`${hintId}-text`} className="sr-only">
          Tu pedido
        </label>
        <textarea
          id={`${hintId}-text`}
          ref={textareaRef}
          rows={1}
          maxLength={MAX_LENGTH}
          value={text}
          aria-describedby={hintId}
          placeholder="Escribí tu pedido"
          onChange={(event) => {
            setText(event.target.value)
            grow(event.target)
          }}
          onKeyDown={handleKeyDown}
          className="max-h-44 min-h-9 flex-1 resize-none bg-transparent px-2 py-1.5 text-[15px] leading-relaxed outline-none placeholder:text-muted"
        />
        <Button type="submit" loading={sending} disabled={blocked || text.trim() === ''}>
          Enviar
        </Button>
      </div>
      <p id={hintId} className="text-xs text-muted">
        {busy ? 'Esperá a que termine la respuesta para seguir.' : 'Enter envía, Shift+Enter agrega una línea. Los cambios te los propone para que los confirmes.'}
      </p>
    </form>
  )
}
