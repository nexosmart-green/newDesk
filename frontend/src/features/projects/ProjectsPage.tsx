import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { FolderSearch } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { PageHeader } from '@/components/ui/PageHeader'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { StaleContent } from '@/components/ui/StaleContent'
import { useCan } from '@/features/auth/useAuth'
import { HoursBar } from '@/features/hours/HoursBar'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { CreateProjectDialog } from './CreateProjectDialog'
import { ProjectsFilters } from './ProjectsFilters'
import { ProjectStatusBadge } from './ProjectStatusBadge'
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
    <section className="max-w-5xl space-y-4">
      <PageHeader title="Proyectos" actions={canCreate && <Button onClick={() => setCreating(true)}>Nuevo proyecto</Button>} />
      <ProjectsFilters filters={filters} onChange={change} />
      {!projects.data ? (
        <PageState isPending={projects.isPending} error={projects.error} shape="list" onRetry={() => void projects.refetch()} />
      ) : (
        <StaleContent stale={projects.isPlaceholderData} className="space-y-4">
          {projects.data.data.length === 0 ? (
            <EmptyState icon={FolderSearch}>No hay proyectos que coincidan con la búsqueda.</EmptyState>
          ) : (
            <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Proyectos">
              {projects.data.data.map((project) => (
                <li key={project.id}>
                  <Link
                    to={`/proyectos/${project.id}`}
                    className="grid gap-2 p-3 hover:bg-bg sm:grid-cols-[6rem_1fr_7rem_auto] sm:items-center sm:gap-3"
                  >
                    <span className="font-mono text-xs uppercase text-muted">{project.code_prefix}</span>
                    <span>
                      <span className="block font-medium">{project.name}</span>
                      <span className="block text-sm text-muted">
                        {[project.client?.name ?? 'Sin cliente', project.leader && `Responsable: ${project.leader.full_name}`, `${project.members_count ?? 0} miembros`]
                          .filter(Boolean)
                          .join(' · ')}
                      </span>
                    </span>
                    <HoursBar bag={project.hours} compact />
                    <ProjectStatusBadge status={project.status} />
                  </Link>
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
