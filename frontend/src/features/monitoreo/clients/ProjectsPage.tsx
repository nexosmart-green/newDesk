import { Fragment, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { useAdminProject, useAllProjects } from '../api/usePortalAdmin'
import { Empty, ErrorNote, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { matchesSearch, partServers } from '../lib/projects'
import { portalStateMeta } from '../lib/status'
import { ProjectLogo } from '../portal/PortalStatusView'
import { vulnTotal } from '../lib/vulns'

// Todos los proyectos de todos los clientes, con su logo (D-048): por el nombre
// del cliente nadie se acuerda de quién es cada cosa.
export function ProjectsPage() {
  const projects = useAllProjects()
  const [query, setQuery] = useState('')

  if (projects.isPending) return <Spinner label="Cargando los proyectos…" />
  if (projects.isError) return <ErrorNote onRetry={() => void projects.refetch()}>{errorMessage(projects.error)}</ErrorNote>
  const shown = projects.data.filter((project) => matchesSearch(query, [project.name, project.client_name, project.slug]))
  // ponytail: el entorno sale de la convención del slug (<proyecto>-staging);
  // si hace falta marcarlo a mano, columna `environment` en portal_projects.
  const groups = [
    { title: 'Producción', items: shown.filter((project) => !project.slug.endsWith('-staging')) },
    { title: 'Staging', items: shown.filter((project) => project.slug.endsWith('-staging')) },
  ].filter((group) => group.items.length > 0)

  return (
    <div className="rise space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="title">Proyectos</h1>
        <span className="num text-sm text-muted">{projects.data.length}</span>
        <Link to="/monitoreo/clientes" className="btn ml-auto">Clientes</Link>
      </div>
      <label className="block max-w-sm">
        <span className="sr-only">Buscar proyecto o cliente</span>
        <input type="search" className="input" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Buscar proyecto o cliente" />
      </label>

      {shown.length === 0 ? (
        <Empty icon="servers">{projects.data.length === 0 ? 'Todavía no hay proyectos. Se crean desde Clientes.' : 'Ningún proyecto coincide con la búsqueda.'}</Empty>
      ) : (
        groups.map((group) => (
          <section key={group.title} aria-label={group.title} className="space-y-3">
            <h2 className="flex items-center gap-2 font-semibold">
              {group.title} <span className="num text-sm font-normal text-muted">{group.items.length}</span>
            </h2>
            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {group.items.map((project) => {
                const meta = portalStateMeta[project.status]
                const lead = project.developers.find((developer) => developer.role === 'lead')
                const vulns = vulnTotal(project.vulns)
                return (
                  <ProjectCard key={project.id} projectId={project.id} className={project.active ? '' : 'opacity-60'}>
                    <Link
                      to={`/monitoreo/clientes/${project.client_id}/proyectos/${project.id}`}
                      className="panel flex items-center gap-4 p-4 transition-shadow hover:shadow-[var(--shadow-pop)]"
                    >
                      <ProjectLogo name={project.name} logoUrl={project.logo_url} className="size-11 rounded-xl text-lg" />
                      <div className="min-w-0 flex-1">
                        <div className="truncate font-semibold">{project.name}</div>
                        <div className="truncate text-sm text-muted">
                          {project.client_name} · {project.components} {project.components === 1 ? 'componente' : 'componentes'}
                        </div>
                        {lead && <div className="truncate text-xs text-muted">{lead.email}</div>}
                      </div>
                      <div className="flex flex-col items-end gap-1">
                        <Badge tone={meta.tone}>{meta.label}</Badge>
                        {vulns > 0 && (
                          <Badge tone={project.vulns.critical + project.vulns.high > 0 ? 'crit' : 'warn'}>
                            {vulns} {vulns === 1 ? 'vulnerabilidad' : 'vulnerabilidades'}
                          </Badge>
                        )}
                      </div>
                    </Link>
                  </ProjectCard>
                )
              })}
            </ul>
          </section>
        ))
      )}
    </div>
  )
}

// Como un title nativo: tras una pausa, junto al cursor, en qué server está cada parte.
// Va por portal a la raíz de la piel (en monitoreo, a body): el .rise de la página anima transform y correría
// el fixed, y fuera de [data-skin] el tooltip perdería los colores del módulo.
// El detalle se pide recién ahí y queda en caché.
function ProjectCard({ projectId, className, children }: { projectId: number; className: string; children: ReactNode }) {
  const [tip, setTip] = useState<{ x: number; y: number } | null>(null)
  const pointer = useRef({ x: 0, y: 0 })
  const timer = useRef<number>(undefined)
  const show = (x: number, y: number) => setTip({ x: Math.min(x, window.innerWidth - 280), y: Math.min(y, window.innerHeight - 90) })
  const hide = () => {
    window.clearTimeout(timer.current)
    setTip(null)
  }
  return (
    <li
      className={className}
      onMouseEnter={() => (timer.current = window.setTimeout(() => show(pointer.current.x + 12, pointer.current.y + 18), 400))}
      onMouseMove={(event) => (pointer.current = { x: event.clientX, y: event.clientY })}
      onMouseLeave={hide}
      onFocus={(event) => {
        const box = event.currentTarget.getBoundingClientRect()
        show(box.left + 16, box.bottom + 6)
      }}
      onBlur={hide}
    >
      {children}
      {tip &&
        createPortal(
          <div
            role="tooltip"
            style={{ left: tip.x, top: tip.y }}
            className="fade-in pointer-events-none fixed z-50 rounded-lg bg-accent px-3 py-2 text-accent-ink shadow-[var(--shadow-pop)]"
          >
            <ProjectPlacement projectId={projectId} />
          </div>,
          document.querySelector('[data-skin="monitoreo"]') ?? document.body,
        )}
    </li>
  )
}

// La DB está siempre en el server del back (D-072).
function ProjectPlacement({ projectId }: { projectId: number }) {
  const project = useAdminProject(projectId)
  if (project.isPending) return <p className="text-xs opacity-70">Cargando…</p>
  if (project.isError) return <p className="text-xs">{errorMessage(project.error)}</p>
  const rows = partServers(project.data.folders)
  if (rows.length === 0) return <p className="text-xs opacity-70">Sin carpetas vinculadas</p>
  return (
    <dl className="grid grid-cols-[auto_auto] gap-x-3 gap-y-0.5 text-xs">
      {rows.map((row) => (
        <Fragment key={row.part}>
          <dt className="opacity-60">{row.part}</dt>
          <dd className="font-medium">{row.servers.join(', ')}</dd>
        </Fragment>
      ))}
    </dl>
  )
}
