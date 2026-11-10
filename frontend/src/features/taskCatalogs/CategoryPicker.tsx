import { useId, useState, type KeyboardEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { SelectField } from '@/components/ui/SelectField'
import { inputClassName } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { useCreateTaskCategory, useTaskCategories } from './useTaskCatalogs'

interface CategoryPickerProps {
  value: number | null
  onChange: (categoryId: number | null) => void
  error?: string
  /** Kept as an option while the catalog loads (or if it was deleted meanwhile). */
  current?: { id: number; name: string } | null
}

const CREATE = 'create'

/** One category per task. "Crear categoría…" adds one inline without leaving the form. */
export function CategoryPicker({ value, onChange, error, current }: CategoryPickerProps) {
  const categories = useTaskCategories()
  const create = useCreateTaskCategory()
  const [creating, setCreating] = useState(false)
  const [name, setName] = useState('')
  const inputId = useId()
  const options = categories.data ?? []
  const showCurrent = current && !options.some((category) => category.id === current.id)

  function submit() {
    if (!name.trim()) return
    create.mutate(name.trim(), {
      onSuccess: (category) => {
        onChange(category.id)
        setCreating(false)
        setName('')
      },
    })
  }

  function cancel() {
    create.reset()
    setCreating(false)
    setName('')
  }

  // A nested <form> isn't valid inside the task form: Enter submits this mini form by hand.
  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Enter') {
      event.preventDefault()
      submit()
    } else if (event.key === 'Escape') {
      event.stopPropagation()
      cancel()
    }
  }

  if (creating) {
    const nameError = fieldErrorOf(create.error, 'name')
    return (
      <div className="space-y-1">
        <label htmlFor={inputId} className="block text-sm text-muted">
          Nueva categoría
        </label>
        <div className="flex gap-2">
          <input
            id={inputId}
            value={name}
            maxLength={80}
            autoFocus
            placeholder="Ej.: Backend-API"
            aria-invalid={nameError ? true : undefined}
            onChange={(event) => setName(event.target.value)}
            onKeyDown={handleKeyDown}
            className={inputClassName}
          />
          <Button onClick={submit} loading={create.isPending} disabled={!name.trim()}>
            Crear
          </Button>
          <Button variant="ghost" onClick={cancel}>
            Cancelar
          </Button>
        </div>
        {nameError && <p className="text-sm text-danger">{nameError}</p>}
      </div>
    )
  }

  return (
    <SelectField
      label="Categoría"
      value={value ?? ''}
      error={error}
      onChange={(event) => {
        const next = event.target.value
        if (next === CREATE) setCreating(true)
        else onChange(next ? Number(next) : null)
      }}
    >
      <option value="">{categories.isPending ? 'Cargando…' : 'Sin categoría'}</option>
      {showCurrent && <option value={current.id}>{current.name}</option>}
      {options.map((category) => (
        <option key={category.id} value={category.id}>
          {category.name}
        </option>
      ))}
      <option value={CREATE}>＋ Crear categoría…</option>
    </SelectField>
  )
}
