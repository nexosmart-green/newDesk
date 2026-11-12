import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { useCan, useCurrentUser } from '@/features/auth/useAuth'
import { PersonSelect } from '@/features/people/PersonSelect'
import { ACTIVE_PROJECTS, useProjects } from '@/features/projects/useProjects'
import { EntryList } from './EntryList'
import { emptyMyEntryFilters, type MyEntryFilters } from './types'
import { useEntries } from './useHours'

/** Closed projects too: old hours were logged in them. */
const ALL_PROJECTS = { ...ACTIVE_PROJECTS, status: '' } as const

/** "Mis horas": every entry of mine across projects, newest first, to fix what I logged (RF-05.15). */
export function MyHoursPage() {
  const { data: user } = useCurrentUser()
  const canManage = useCan('time.manage')
  const projects = useProjects(ALL_PROJECTS)
  const [filters, setFilters] = useState(emptyMyEntryFilters)
  const entries = useEntries(filters)
  const filtered = filters.user_id !== null || filters.project_id !== null || filters.from !== '' || filters.to !== ''

  const change = (changes: Partial<MyEntryFilters>) =>
    setFilters((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  return (
    <section className="max-w-3xl space-y-4">
      <h1 className="text-lg font-semibold">Mis horas</h1>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {canManage && (
          <PersonSelect
            label="Persona"
            emptyLabel="Yo"
            value={filters.user_id}
            excludeIds={user ? [user.id] : []}
            onChange={(user_id) => change({ user_id })}
          />
        )}
        <SelectField
          label="Proyecto"
          value={filters.project_id ?? ''}
          onChange={(event) => change({ project_id: event.target.value ? Number(event.target.value) : null })}
        >
          <option value="">Todos</option>
          {projects.data?.data.map((project) => (
            <option key={project.id} value={project.id}>
              {project.name}
            </option>
          ))}
        </SelectField>
        <TextField label="Desde" type="date" value={filters.from} onChange={(event) => change({ from: event.target.value })} />
        <TextField label="Hasta" type="date" value={filters.to} onChange={(event) => change({ to: event.target.value })} />
      </div>
      {!entries.data ? (
        <PageState isPending={entries.isPending} error={entries.error} onRetry={() => void entries.refetch()} />
      ) : (
        <EntryList
          page={entries.data}
          empty={filtered ? 'No hay registros con estos filtros.' : 'Todavía no cargaste horas.'}
          showProject
          onPageChange={(page) => change({ page })}
        />
      )}
    </section>
  )
}
