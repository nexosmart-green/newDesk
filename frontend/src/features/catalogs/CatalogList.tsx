import { useId, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { Skeleton } from '@/components/ui/Skeleton'
import { inputClassName } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { CatalogItemRow } from './CatalogItemRow'
import { catalogLabels, newCatalogItemLabel, type CatalogType } from './types'
import { useCatalog, useCreateCatalogItem } from './useCatalogs'

export function CatalogList({ type }: { type: CatalogType }) {
  const labels = catalogLabels[type]
  const headingId = useId()
  const inputId = useId()
  const catalog = useCatalog(type)
  const create = useCreateCatalogItem(type)
  const [name, setName] = useState('')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!name.trim()) return
    create.mutate(name.trim(), { onSuccess: () => setName('') })
  }

  const nameError = fieldErrorOf(create.error, 'name')

  return (
    <section aria-labelledby={headingId} className="space-y-3 rounded-lg border border-border bg-surface p-4">
      <h2 id={headingId} className="font-semibold">
        {labels.title}
      </h2>
      {!catalog.data ? (
        <PageState isPending={catalog.isPending} error={catalog.error} skeleton={<CatalogSkeleton />} onRetry={() => void catalog.refetch()} />
      ) : catalog.data.length === 0 ? (
        <p className="text-sm text-muted">Todavía no hay valores.</p>
      ) : (
        <ul className="divide-y divide-border" aria-label={labels.title}>
          {catalog.data.map((item) => (
            <CatalogItemRow key={item.id} type={type} item={item} />
          ))}
        </ul>
      )}
      <form onSubmit={handleSubmit} className="space-y-1" noValidate>
        <label htmlFor={inputId} className="sr-only">
          {newCatalogItemLabel(type)}
        </label>
        <div className="flex gap-2">
          <input
            id={inputId}
            value={name}
            placeholder={newCatalogItemLabel(type)}
            maxLength={80}
            aria-invalid={nameError ? true : undefined}
            onChange={(event) => setName(event.target.value)}
            className={inputClassName}
          />
          <Button type="submit" loading={create.isPending} disabled={!name.trim()}>
            Agregar
          </Button>
        </div>
        {nameError && <p className="text-sm text-danger">{nameError}</p>}
      </form>
    </section>
  )
}

function CatalogSkeleton() {
  return (
    <div role="status" className="space-y-3 py-1">
      <span className="sr-only">Cargando…</span>
      <Skeleton className="h-4 w-2/3" />
      <Skeleton className="h-4 w-1/2" />
      <Skeleton className="h-4 w-3/5" />
    </div>
  )
}
