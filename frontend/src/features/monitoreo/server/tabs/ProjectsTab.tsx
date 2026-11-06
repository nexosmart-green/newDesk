import { Fragment, useId, useState } from 'react'
import { useSearchParams } from 'react-router'
import type { LargeFiles, Project, ProjectLargeFiles, Projects } from '../../api/contract'
import { useProjectIndex } from '../../api/useMonitoring'
import type { FolderLink } from '../../api/types'
import { ClientTag } from '../../components/ClientTag'
import { Pagination } from '../../components/DataTable'
import { Icon } from '../../components/Icon'
import { Empty, Panel, TableWrap } from '../../components/Panel'
import { Badge } from '../../components/Status'
import { formatBytes, formatDateTime, formatRelative } from '../../lib/format'
import { usePage } from '../../lib/paginate'
import { matchesSearch } from '../../lib/projects'

export function ProjectsTab({ data, largeFiles, serverId }: { data: Projects; largeFiles?: LargeFiles | null; serverId?: number }) {
  const items = data.items ?? []
  // De qué cliente es cada carpeta (D-044): sale del índice de inventario, que ya trae la vinculación.
  const index = useProjectIndex(serverId !== undefined)
  const links = new Map((index.data ?? []).flatMap((item) => (item.server_id === serverId && item.linked ? [[item.path, item.linked] as const] : [])))
  const heavyByProject = new Map((largeFiles?.projects ?? []).map((entry) => [entry.project, entry]))
  const [open, setOpen] = useState<Record<string, boolean>>({})
  const toggle = (path: string) => setOpen((current) => ({ ...current, [path]: !current[path] }))
  // El buscador de Servidores llega con ?q=<proyecto>.
  const [params] = useSearchParams()
  const [filter, setFilter] = useState(() => params.get('q') ?? '')
  const filterId = useId()
  const visible = filter.trim()
    ? items.filter((project) => matchesSearch(filter, [project.name, project.path, project.type, ...(project.subapps ?? []).map((sub) => sub.path)]))
    : items
  // Se pagina por proyecto: las subapps desplegadas no cuentan como filas.
  const page = usePage(visible)

  return (
    <div className="space-y-5">
      <Panel
        title={visible.length === items.length ? `Proyectos · ${items.length}` : `Proyectos · ${visible.length} de ${items.length}`}
        actions={
          items.length > 0 && (
            <>
              <label htmlFor={filterId} className="sr-only">Filtrar proyectos</label>
              <input
                id={filterId}
                type="search"
                className="input h-8 w-56 text-xs"
                placeholder="Filtrar proyectos…"
                value={filter}
                onChange={(event) => {
                  setFilter(event.target.value)
                  page.setPage(1)
                }}
              />
            </>
          )
        }
        bodyClassName=""
      >
        {items.length === 0 ? (
          <Empty>No se detectaron proyectos.</Empty>
        ) : visible.length === 0 ? (
          <Empty>Ningún proyecto coincide con "{filter.trim()}".</Empty>
        ) : (
          <TableWrap>
            <table className="table">
              <thead>
                <tr>
                  <th>Proyecto</th>
                  <th>Tipo</th>
                  <th>Git</th>
                  <th>Último commit</th>
                  <th>Dueño</th>
                </tr>
              </thead>
              <tbody key={page.page} className={page.pages > 1 ? 'fade-in' : undefined}>
                {page.rows.map((project) => (
                  <Fragment key={project.path}>
                    <ProjectRow project={project} heavy={heavyByProject.get(project.path)} link={links.get(project.path)} expanded={Boolean(open[project.path])} onToggle={() => toggle(project.path)} />
                    {open[project.path] &&
                      (project.subapps ?? []).map((sub) => (
                        <tr key={sub.path} className="fade-in bg-surface-2/50">
                          <td className="pl-9 font-mono text-xs text-muted">└ {sub.path}</td>
                          <td className="text-sm" colSpan={4}>
                            {sub.type}
                            {sub.framework_version && <span className="text-faint"> {sub.framework_version}</span>}
                          </td>
                        </tr>
                      ))}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </TableWrap>
        )}
        <Pagination page={page.page} pages={page.pages} total={page.total} noun="proyectos" onChange={page.setPage} />
      </Panel>
      {(data.discarded ?? []).length > 0 && (
        <details className="group panel px-5 py-3.5 text-sm">
          <summary className="flex items-center gap-2 text-muted">
            <Icon name="chevron" size={12} className="transition-transform duration-[var(--dur-2)] group-open:rotate-90" />
            Carpetas descartadas · {data.discarded?.length}
          </summary>
          <ul className="mt-2 space-y-1 pl-5 font-mono text-xs text-muted">
            {data.discarded?.map((dir) => (
              <li key={dir.path}>
                {dir.path} <span className="text-faint">— {dir.reason}</span>
              </li>
            ))}
          </ul>
        </details>
      )}
    </div>
  )
}

function ProjectRow({ project, heavy, link, expanded, onToggle }: {
  project: Project; heavy?: ProjectLargeFiles | undefined; link?: FolderLink | undefined; expanded: boolean; onToggle: () => void
}) {
  const git = project.git
  const subapps = project.subapps?.length ?? 0
  return (
    <tr>
      <td className="min-w-[240px]">
        <div className="flex items-start gap-1.5">
          {subapps > 0 ? (
            <button type="button" onClick={onToggle} aria-expanded={expanded} aria-label={`Subapps de ${project.name}`} className="mt-0.5 text-muted transition-colors hover:text-text">
              <Icon name="chevron" size={14} className={`transition-transform duration-[var(--dur-2)] ${expanded ? 'rotate-90' : ''}`} />
            </button>
          ) : (
            <span className="w-3.5" />
          )}
          <div className="min-w-0">
            <div className="font-medium">{project.name}</div>
            <div className="truncate font-mono text-xs text-faint" title={project.path}>{project.path}</div>
            {project.outside_web_root && <span className="tag">fuera de /var/www</span>}
            {link && <ClientTag link={link} />}
            {heavy && heavy.count > 0 && (
              <Badge tone="warn">
                {heavy.count} {heavy.count === 1 ? 'archivo pesado' : 'archivos pesados'} · {formatBytes(heavy.total_bytes)}
              </Badge>
            )}
          </div>
        </div>
      </td>
      <td className="num whitespace-nowrap text-sm">
        {project.type}
        {project.framework_version && <span className="text-faint"> {project.framework_version}</span>}
        {subapps > 0 && <div className="text-xs text-muted">{subapps} subapps</div>}
      </td>
      <td className="num whitespace-nowrap text-sm">
        {git ? (
          <>
            <div>
              {git.branch} <span className="text-faint">@{git.commit}</span>
            </div>
            {git.dirty_files > 0 ? <Badge tone="warn">{git.dirty_files} sin commitear</Badge> : <span className="text-xs text-ok">limpio</span>}
            {git.error && <div className="text-xs text-crit">{git.error}</div>}
          </>
        ) : (
          <span className="text-faint">sin git</span>
        )}
      </td>
      <td className="max-w-[340px] text-sm">
        {git ? (
          <>
            <div className="truncate" title={git.subject}>{git.subject}</div>
            <div className="num text-xs text-faint" title={formatDateTime(git.commit_date)}>
              {git.author} · {formatRelative(git.commit_date)}
              {git.remote_repo && ` · ${git.remote_repo}`}
            </div>
          </>
        ) : (
          '—'
        )}
      </td>
      <td className="font-mono text-xs whitespace-nowrap text-muted">
        {project.owner} <span className="text-faint">{project.mode}</span>
      </td>
    </tr>
  )
}
