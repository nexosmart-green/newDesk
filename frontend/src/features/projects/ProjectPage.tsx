import { Link, Outlet, useParams } from 'react-router'
import { BackLink } from '@/components/ui/BackLink'
import { PageState } from '@/components/ui/PageState'
import { TabNav } from '@/components/ui/TabNav'
import { ApiError } from '@/lib/api'
import { ProjectStatusControl } from './ProjectStatusControl'
import { useProject } from './useProjects'

/** RF-03.04: Resumen · Tareas · Horas · Miembros · Actividad, each tab a route. */
export function ProjectPage() {
  const projectId = Number(useParams().projectId)
  const project = useProject(projectId)

  // CA-03.03: non-members get 404 — we don't confirm the project exists.
  if (project.error instanceof ApiError && project.error.status === 404) {
    return (
      <section className="space-y-2">
        <h1 className="text-xl font-semibold leading-tight tracking-tight">Proyecto no encontrado</h1>
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
    <section className="max-w-5xl space-y-6">
      <div className="space-y-2">
        <BackLink to="/proyectos">Proyectos</BackLink>
        <header className="space-y-1">
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h1 className="text-xl font-semibold leading-tight tracking-tight">{project.data.name}</h1>
            <span className="text-sm text-muted">{project.data.code_prefix}</span>
            <ProjectStatusControl project={project.data} />
          </div>
          {project.data.client && <p className="text-sm text-muted">{project.data.client.name}</p>}
        </header>
      </div>
      <TabNav label="Secciones del proyecto" tabs={tabs} />
      <Outlet context={project.data} />
    </section>
  )
}
