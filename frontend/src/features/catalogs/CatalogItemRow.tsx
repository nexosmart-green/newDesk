import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { inputClassName } from '@/components/ui/TextField'
import type { CatalogItem, CatalogType } from './types'
import { useDeleteCatalogItem, useRenameCatalogItem } from './useCatalogs'

export function CatalogItemRow({ type, item }: { type: CatalogType; item: CatalogItem }) {
  const [draft, setDraft] = useState<string | null>(null)
  const rename = useRenameCatalogItem(type)
  const remove = useDeleteCatalogItem(type)

  function handleRename(event: FormEvent) {
    event.preventDefault()
    if (draft === null || !draft.trim() || draft.trim() === item.name) return setDraft(null)
    rename.mutate({ id: item.id, name: draft.trim() }, { onSuccess: () => setDraft(null) })
  }

  return (
    <li className="space-y-1 py-2">
      {draft === null ? (
        <div className="flex items-center justify-between gap-2">
          <span className="text-sm">{item.name}</span>
          <div className="flex gap-3">
            <Button variant="link" aria-label={`Renombrar ${item.name}`} onClick={() => setDraft(item.name)}>
              Renombrar
            </Button>
            <Button
              variant="link"
              className="text-danger"
              aria-label={`Borrar ${item.name}`}
              loading={remove.isPending}
              onClick={() => remove.mutate(item.id)}
            >
              Borrar
            </Button>
          </div>
        </div>
      ) : (
        <form onSubmit={handleRename} className="flex gap-2">
          <input
            aria-label={`Nuevo nombre para ${item.name}`}
            value={draft}
            maxLength={80}
            autoFocus
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === 'Escape') setDraft(null)
            }}
            className={inputClassName}
          />
          <Button type="submit" loading={rename.isPending}>
            Guardar
          </Button>
        </form>
      )}
      <FieldErrorsAlert error={rename.error ?? remove.error} />
    </li>
  )
}
