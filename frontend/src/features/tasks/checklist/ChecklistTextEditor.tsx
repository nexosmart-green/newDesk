import { LoaderCircle } from 'lucide-react'
import { useId, useState, type KeyboardEvent } from 'react'
import { inputClassName } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { ChecklistItem } from '../types'
import { useRenameChecklistItem } from './useChecklist'

export const CHECKLIST_TEXT_MAX = 500

interface ChecklistTextEditorProps {
  code: string
  item: ChecklistItem
  onDone: () => void
}

/** Inline rename: Enter or leaving the field saves, Escape cancels. The error stays under the field. */
export function ChecklistTextEditor({ code, item, onDone }: ChecklistTextEditorProps) {
  const id = useId()
  const rename = useRenameChecklistItem(code)
  const [text, setText] = useState(item.text)
  const error = fieldErrorOf(rename.error, 'text')

  function save() {
    const trimmed = text.trim()
    if (rename.isPending) return
    if (!trimmed || trimmed === item.text) return onDone()
    rename.mutate({ id: item.id, text: trimmed }, { onSuccess: onDone })
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Enter') {
      event.preventDefault()
      save()
    } else if (event.key === 'Escape') {
      event.preventDefault()
      event.stopPropagation()
      onDone()
    }
  }

  return (
    <div className="min-w-0 flex-1">
      <div className="relative">
        <input
          id={id}
          autoFocus
          value={text}
          maxLength={CHECKLIST_TEXT_MAX}
          aria-label="Texto de la subtarea"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${id}-error` : undefined}
          aria-busy={rename.isPending || undefined}
          onChange={(event) => setText(event.target.value)}
          onKeyDown={handleKeyDown}
          onBlur={save}
          className={`${inputClassName} pr-9`}
        />
        {rename.isPending && (
          <LoaderCircle aria-hidden="true" className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-accent" />
        )}
      </div>
      {error && (
        <p id={`${id}-error`} className="mt-1 text-xs text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
