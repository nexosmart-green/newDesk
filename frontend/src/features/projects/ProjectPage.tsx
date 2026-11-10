import { Link, Outlet, useParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { TabNav } from '@/components/ui/TabNav'
import { ApiError } from '@/lib/api'
import { ProjectStatusBadge } from './ProjectStatusBadge'
import { useProject } from './useProjects'

/** RF-03.04: Resumen · Tareas · Horas · Miembros · Actividad, each tab a route. */
export function ProjectPage() {
  const projectId = Number(useParams().projectId)
  const project = useProject(projectId)

  // CA-03.03: non-members get 404 — we don't confirm the project exists.
  if (project.error instanceof ApiError && project.error.status === 404) {
    return (
      <section className="space-y-2">
        <h1 className="text-lg font-semibold">Proyecto no encontrado</h1>
        <p className="text-sm text-muted">No existe o no sos miembro de este proyecto.</p>
        <Link to="/proyectos" className="text-sm text-accent">
          Volver a Proyectos
        </Link>
      </section>
    )
  }
  if (!project.data) return <PageState isPending={project.isPending} error={project.error} shape="detail" onRetry={() => void project.refetch()} />

  const base = `/proyectos/${project.data.id}`
  const tabs = [
    { path: base, label: 'Resumen' },
    { path: `${base}/tareas`, label: 'Tareas' },
    { path: `${base}/horas`, label: 'Horas' },
    { path: `${base}/miembros`, label: 'Miembros' },
    { path: `${base}/actividad`, label: 'Actividad' },
  ]

  return (
    <section className="max-w-5xl space-y-4">
      <Link to="/proyectos" className="text-sm text-accent">
        ← Proyectos
      </Link>
      <header className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <span className="font-mono text-sm uppercase text-muted">{project.data.code_prefix}</span>
        <h1 className="text-lg font-semibold">{project.data.name}</h1>
        <ProjectStatusBadge status={project.data.status} />
        {project.data.client && (
          <span className="w-full text-sm text-muted sm:w-auto">{project.data.client.name}</span>
        )}
      </header>
      <TabNav label="Secciones del proyecto" tabs={tabs} />
      <Outlet context={project.data} />
    </section>
  )
}
