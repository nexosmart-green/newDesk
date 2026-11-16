import { useId, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { ListSkeleton } from '@/components/ui/Skeleton'
import { inputClassName } from '@/components/ui/TextField'
import type { TaskCategory } from '@/features/tasks/types'
import { fieldErrorOf } from '@/lib/api'
import { tasksCountText } from './catalogFormat'
import { CatalogPanel } from './CatalogPanel'
import { useCreateTaskCategory, useDeleteTaskCategory, useRenameTaskCategory, useTaskCategories } from './useTaskCatalogs'

/** Configuración → Catálogos: task categories (`projects.manage` renames and deletes). */
export function TaskCategoriesPanel() {
  const categories = useTaskCategories()
  const create = useCreateTaskCategory()
  const inputId = useId()
  const [name, setName] = useState('')
  const nameError = fieldErrorOf(create.error, 'name')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (name.trim()) create.mutate(name.trim(), { onSuccess: () => setName('') })
  }

  return (
    <CatalogPanel title="Categorías de tarea" description="Agrupan el tipo de trabajo. Cada tarea tiene una como máximo.">
      {!categories.data ? (
        <PageState
          isPending={categories.isPending}
          error={categories.error}
          skeleton={<ListSkeleton rows={3} label="Cargando categorías…" />}
          onRetry={() => void categories.refetch()}
        />
      ) : categories.data.length === 0 ? (
        <p className="text-sm text-muted">Todavía no hay categorías. Creá la primera abajo.</p>
      ) : (
        <ul className="divide-y divide-border" aria-label="Categorías de tarea">
          {categories.data.map((category) => (
            <CategoryRow key={category.id} category={category} />
          ))}
        </ul>
      )}
      <form onSubmit={handleSubmit} className="space-y-1" noValidate>
        <label htmlFor={inputId} className="sr-only">
          Nueva categoría
        </label>
        <div className="flex gap-2">
          <input
            id={inputId}
            value={name}
            placeholder="Nueva categoría"
            maxLength={80}
            aria-invalid={nameError ? true : undefined}
            onChange={(event) => setName(event.target.value)}
            className={inputClassName}
          />
          <Button type="submit" loading={create.isPending} disabled={!name.trim()}>
            Crear categoría
          </Button>
        </div>
        {nameError && <p className="text-sm text-danger">{nameError}</p>}
      </form>
    </CatalogPanel>
  )
}

function CategoryRow({ category }: { category: TaskCategory }) {
  const rename = useRenameTaskCategory()
  const remove = useDeleteTaskCategory()
  const [draft, setDraft] = useState<string | null>(null)
  const [confirming, setConfirming] = useState(false)

  function handleRename(event: FormEvent) {
    event.preventDefault()
    if (draft === null || !draft.trim() || draft.trim() === category.name) return setDraft(null)
    rename.mutate({ id: category.id, name: draft.trim() }, { onSuccess: () => setDraft(null) })
  }

  return (
    <li className="space-y-1 py-2">
      {draft === null ? (
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="min-w-0">
            <p className="text-sm font-medium">{category.name}</p>
            <p className="text-xs text-muted">{tasksCountText(category.tasks_count)}</p>
          </div>
          <div className="flex gap-1">
            <Button variant="ghost" aria-label={`Renombrar ${category.name}`} onClick={() => setDraft(category.name)}>
              Renombrar
            </Button>
            <Button variant="ghost" className="hover:text-danger" aria-label={`Borrar ${category.name}`} onClick={() => setConfirming(true)}>
              Borrar
            </Button>
          </div>
        </div>
      ) : (
        <form onSubmit={handleRename} className="space-y-1">
          <div className="flex gap-2">
            <input
              aria-label={`Nuevo nombre para ${category.name}`}
              value={draft}
              maxLength={80}
              autoFocus
              aria-invalid={fieldErrorOf(rename.error, 'name') ? true : undefined}
              onChange={(event) => setDraft(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Escape') setDraft(null)
              }}
              className={inputClassName}
            />
            <Button type="submit" loading={rename.isPending}>
              Guardar
            </Button>
          </div>
          {fieldErrorOf(rename.error, 'name') && <p className="text-sm text-danger">{fieldErrorOf(rename.error, 'name')}</p>}
        </form>
      )}
      {confirming && (
        <ConfirmDialog
          title={`¿Borrar «${category.name}»?`}
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(category.id, { onSuccess: () => setConfirming(false) })}
          onClose={() => setConfirming(false)}
        >
          <p>
            {category.tasks_count ? `${tasksCountText(category.tasks_count)} quedan sin categoría.` : 'Ninguna tarea la usa.'} No se
            puede deshacer.
          </p>
        </ConfirmDialog>
      )}
    </li>
  )
}
