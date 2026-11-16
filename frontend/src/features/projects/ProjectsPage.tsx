import { useState } from 'react'
import { useNavigate } from 'react-router'
import { FolderSearch } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader } from '@/components/ui/PageHeader'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { StaleContent } from '@/components/ui/StaleContent'
import { useCan } from '@/features/auth/useAuth'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { CreateProjectDialog } from './CreateProjectDialog'
import { ProjectsFilters } from './ProjectsFilters'
import { ProjectRow } from './ProjectRow'
import type { ProjectFilters } from './types'
import { useProjects } from './useProjects'

const initialFilters: ProjectFilters = { client_id: null, status: '', search: '', running_out: false, page: 1 }

/** RF-03.07. Collaborators only get their projects (backend scope). */
export function ProjectsPage() {
  const navigate = useNavigate()
  const canCreate = useCan('projects.manage')
  const [filters, setFilters] = useState(initialFilters)
  const [creating, setCreating] = useState(false)
  const projects = useProjects({ ...filters, search: useDebouncedValue(filters.search) })

  const change = (changes: Partial<ProjectFilters>) => setFilters((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  return (
    <section className="max-w-5xl space-y-6">
      <PageHeader title="Proyectos" actions={canCreate && <Button onClick={() => setCreating(true)}>Nuevo proyecto</Button>} />
      <ProjectsFilters filters={filters} onChange={change} />
      {!projects.data ? (
        <PageState isPending={projects.isPending} error={projects.error} shape="list" onRetry={() => void projects.refetch()} />
      ) : (
        <StaleContent stale={projects.isPlaceholderData} className="space-y-4">
          {projects.data.data.length === 0 ? (
            <EmptyState icon={FolderSearch}>No hay proyectos que coincidan con la búsqueda.</EmptyState>
          ) : (
            <ul className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface" aria-label="Proyectos">
              {projects.data.data.map((project) => (
                <li key={project.id}>
                  <ProjectRow project={project} />
                </li>
              ))}
            </ul>
          )}
          <Pagination meta={projects.data.meta} onPageChange={(page) => change({ page })} />
        </StaleContent>
      )}
      {creating && (
        <CreateProjectDialog onClose={() => setCreating(false)} onCreated={(project) => navigate(`/proyectos/${project.id}`)} />
      )}
    </section>
  )
}
