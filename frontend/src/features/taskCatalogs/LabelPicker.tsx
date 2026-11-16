import { Check, Plus } from 'lucide-react'
import { useId, useState, type KeyboardEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Skeleton'
import { inputClassName } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { DEFAULT_LABEL_COLOR } from './catalogFormat'
import { ColorSwatches } from './ColorSwatches'
import { useCreateTaskLabel, useTaskLabels } from './useTaskCatalogs'

interface LabelPickerProps {
  selected: number[]
  onChange: (labelIds: number[]) => void
  error?: string
}

/** N labels per task: each one is a toggle chip; "Nueva etiqueta" creates and selects it in place. */
export function LabelPicker({ selected, onChange, error }: LabelPickerProps) {
  const labels = useTaskLabels()
  const [creating, setCreating] = useState(false)

  function toggle(id: number) {
    onChange(selected.includes(id) ? selected.filter((current) => current !== id) : [...selected, id])
  }

  return (
    <fieldset className="space-y-2">
      <legend className="text-sm text-muted">Etiquetas</legend>
      {labels.isPending ? (
        <div role="status" className="flex gap-2">
          <span className="sr-only">Cargando etiquetas…</span>
          <Skeleton className="h-9 w-20 rounded-full" />
          <Skeleton className="h-9 w-24 rounded-full" />
        </div>
      ) : (
        <div className="flex flex-wrap gap-2">
          {(labels.data ?? []).map((label) => {
            const on = selected.includes(label.id)
            return (
              <button
                key={label.id}
                type="button"
                aria-pressed={on}
                onClick={() => toggle(label.id)}
                className={`inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full border px-3 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent ${on ? 'font-medium text-text' : 'border-border text-muted hover:text-text'}`}
                style={on ? { backgroundColor: `${label.color}2e`, borderColor: label.color } : undefined}
              >
                {on ? (
                  <Check aria-hidden="true" className="size-3.5" strokeWidth={2.5} />
                ) : (
                  <span aria-hidden="true" className="size-2 rounded-full" style={{ backgroundColor: label.color }} />
                )}
                {label.name}
              </button>
            )
          })}
          {!creating && (
            <button
              type="button"
              onClick={() => setCreating(true)}
              className="inline-flex min-h-9 cursor-pointer items-center gap-1 rounded-full border border-dashed border-border px-3 text-sm text-muted transition-colors hover:border-accent hover:text-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
              <Plus aria-hidden="true" className="size-3.5" />
              Nueva etiqueta
            </button>
          )}
        </div>
      )}
      {creating && (
        <NewLabelForm
          onCreated={(id) => {
            onChange([...selected, id])
            setCreating(false)
          }}
          onCancel={() => setCreating(false)}
        />
      )}
      {error && <p className="text-sm text-danger">{error}</p>}
    </fieldset>
  )
}

function NewLabelForm({ onCreated, onCancel }: { onCreated: (id: number) => void; onCancel: () => void }) {
  const create = useCreateTaskLabel()
  const inputId = useId()
  const [name, setName] = useState('')
  const [color, setColor] = useState<string>(DEFAULT_LABEL_COLOR)
  const nameError = fieldErrorOf(create.error, 'name')

  function submit() {
    if (!name.trim()) return
    create.mutate({ name: name.trim(), color }, { onSuccess: (label) => onCreated(label.id) })
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Enter') {
      event.preventDefault()
      submit()
    } else if (event.key === 'Escape') {
      event.stopPropagation()
      onCancel()
    }
  }

  return (
    <div className="space-y-3 rounded-lg border border-border bg-surface-2 p-3">
      <div className="space-y-1">
        <label htmlFor={inputId} className="block text-sm text-muted">
          Nombre de la etiqueta
        </label>
        <input
          id={inputId}
          value={name}
          maxLength={50}
          autoFocus
          placeholder="Ej.: Urgente cliente"
          aria-invalid={nameError ? true : undefined}
          onChange={(event) => setName(event.target.value)}
          onKeyDown={handleKeyDown}
          className={inputClassName}
        />
        {nameError && <p className="text-sm text-danger">{nameError}</p>}
      </div>
      <ColorSwatches value={color} onChange={setColor} error={fieldErrorOf(create.error, 'color')} />
      <div className="flex justify-end gap-2">
        <Button variant="ghost" onClick={onCancel}>
          Cancelar
        </Button>
        <Button onClick={submit} loading={create.isPending} disabled={!name.trim()}>
          Crear etiqueta
        </Button>
      </div>
    </div>
  )
}
