import { Plus } from 'lucide-react'
import { useId, useState, type FormEvent, type Ref } from 'react'
import { Button } from '@/components/ui/Button'
import { inputClassName } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { CHECKLIST_TEXT_MAX } from './ChecklistTextEditor'
import { useAddChecklistItem } from './useChecklist'

export const CHECKLIST_MAX_ITEMS = 100

interface ChecklistAddFormProps {
  code: string
  count: number
  inputRef: Ref<HTMLInputElement>
}

/** Enter adds and keeps the cursor here, so a list of steps is typed in one go. */
export function ChecklistAddForm({ code, count, inputRef }: ChecklistAddFormProps) {
  const id = useId()
  const add = useAddChecklistItem(code)
  const [text, setText] = useState('')
  const full = count >= CHECKLIST_MAX_ITEMS
  const error = fieldErrorOf(add.error, 'text')
  const hint = full ? `Llegaste al máximo de ${CHECKLIST_MAX_ITEMS} subtareas.` : null

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const trimmed = text.trim()
    if (!trimmed || add.isPending || full) return
    add.mutate(trimmed, { onSuccess: () => setText('') })
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-1" aria-label="Agregar subtarea">
      <div className="flex gap-2">
        <label htmlFor={id} className="sr-only">
          Nueva subtarea
        </label>
        <input
          ref={inputRef}
          id={id}
          value={text}
          maxLength={CHECKLIST_TEXT_MAX}
          disabled={full}
          placeholder="Agregar una subtarea y Enter"
          aria-invalid={error ? true : undefined}
          aria-describedby={error || hint ? `${id}-message` : undefined}
          onChange={(event) => {
            setText(event.target.value)
            if (add.error) add.reset()
          }}
          className={inputClassName}
        />
        <Button type="submit" variant="secondary" loading={add.isPending} disabled={!text.trim() || full} className="shrink-0">
          <Plus aria-hidden="true" className="size-4" />
          <span className="sr-only sm:not-sr-only">Agregar</span>
        </Button>
      </div>
      {(error || hint) && (
        <p id={`${id}-message`} className={error ? 'text-xs text-danger' : 'text-xs text-muted'}>
          {error ?? hint}
        </p>
      )}
    </form>
  )
}
