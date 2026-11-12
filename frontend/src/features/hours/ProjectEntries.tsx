import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import type { ProjectDetail } from '@/features/projects/types'
import { EntryFilters } from './EntryFilters'
import { EntryList } from './EntryList'
import { emptyProjectEntryFilters, type ProjectEntryFilters } from './types'
import { useProjectEntries } from './useHours'

/** RF-05.11: entries by person / task / date, with their comments (RF-05.18). */
export function ProjectEntries({ project }: { project: ProjectDetail }) {
  const [filters, setFilters] = useState(emptyProjectEntryFilters)
  const entries = useProjectEntries(project.id, filters)

  const change = (changes: Partial<ProjectEntryFilters>) =>
    setFilters((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  return (
    <section className="space-y-3" aria-labelledby="project-entries">
      <h2 id="project-entries" className="font-semibold">
        Registros
      </h2>
      <EntryFilters project={project} filters={filters} onChange={change} />
      {!entries.data ? (
        <PageState isPending={entries.isPending} error={entries.error} onRetry={() => void entries.refetch()} />
      ) : (
        <EntryList page={entries.data} empty="No hay registros con estos filtros." onPageChange={(page) => change({ page })} />
      )}
    </section>
  )
}
