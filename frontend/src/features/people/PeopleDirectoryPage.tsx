import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { useCan } from '@/features/auth/useAuth'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { PeopleFilters } from './PeopleFilters'
import { PersonCard } from './PersonCard'
import { emptyDirectoryFilters, type DirectoryFilters } from './types'
import { usePeople } from './usePeople'

export function PeopleDirectoryPage() {
  const [filters, setFilters] = useState<DirectoryFilters>(emptyDirectoryFilters)
  const debouncedSearch = useDebouncedValue(filters.search)
  const people = usePeople({ ...filters, search: debouncedSearch })
  const canManageUsers = useCan('users.manage')

  function handleChange(changes: Partial<DirectoryFilters>) {
    setFilters((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  }

  return (
    <section className="mx-auto max-w-5xl space-y-4">
      <h1 className="text-lg font-semibold">Personas</h1>
      <PeopleFilters filters={filters} canSeeInactive={canManageUsers} onChange={handleChange} />
      {!people.data ? (
        <PageState isPending={people.isPending} error={people.error} />
      ) : people.data.data.length === 0 ? (
        <p className="text-sm text-muted">No hay personas que coincidan con la búsqueda.</p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Personas">
          {people.data.data.map((person) => (
            <li key={person.id}>
              <PersonCard person={person} />
            </li>
          ))}
        </ul>
      )}
      {people.data && <Pagination meta={people.data.meta} onPageChange={(page) => handleChange({ page })} />}
    </section>
  )
}
