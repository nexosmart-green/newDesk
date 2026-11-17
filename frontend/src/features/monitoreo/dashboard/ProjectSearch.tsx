import { Link } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { useProjectIndex } from '../api/useMonitoring'
import type { IndexedProject } from '../api/types'
import { ClientTag } from '../components/ClientTag'
import { DataTable } from '../components/DataTable'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { ServerStatusBadge } from '../components/Status'
import { matchesSearch, searchable } from '../lib/projects'

// Primero los que coinciden por nombre; después por ruta, dominio, server o tipo.
function search(index: readonly IndexedProject[], query: string): IndexedProject[] {
  const needle = searchable(query.trim())
  const rank = (project: IndexedProject) => {
    const name = searchable(project.name)
    return name.startsWith(needle) ? 0 : name.includes(needle) ? 1 : 2
  }
  return index
    .filter((project) => matchesSearch(query, [project.name, project.path, project.server_name, project.type, ...project.domains, ...project.subapps]))
    .sort((a, b) => rank(a) - rank(b))
}

export function ProjectSearch({ query }: { query: string }) {
  const index = useProjectIndex()
  const results = index.data ? search(index.data, query) : []

  return (
    <Panel title={index.data ? `Proyectos · ${results.length}` : 'Proyectos'} bodyClassName="" className="fade-in">
      {index.isPending && (
        <div className="px-5 pb-5">
          <Spinner label="Buscando proyectos…" />
        </div>
      )}
      {index.isError && (
        <div className="px-5 pb-5">
          <ErrorNote onRetry={() => void index.refetch()}>{errorMessage(index.error)}</ErrorNote>
        </div>
      )}
      {index.data && (
        <DataTable<IndexedProject>
          key={query} // vuelve a la página 1 con cada búsqueda
          noun="proyectos"
          rows={results}
          rowKey={(row) => `${row.server_id}:${row.path}`}
          empty={`Ningún proyecto coincide con "${query.trim()}".`}
          columns={[
            {
              header: 'Proyecto',
              cell: (row) => (
                <div className="min-w-0">
                  <Link to={`/monitoreo/servers/${row.server_id}/proyectos?q=${encodeURIComponent(row.name)}`} className="link font-medium">
                    {row.name}
                  </Link>
                  <div className="max-w-[320px] truncate font-mono text-xs text-faint" title={row.path}>{row.path}</div>
                  {row.linked && <div className="mt-1"><ClientTag link={row.linked} /></div>}
                </div>
              ),
            },
            {
              header: 'Dominios',
              cell: (row) =>
                row.domains.length > 0 ? (
                  <ul className="space-y-0.5 text-sm">
                    {row.domains.map((domain) => <li key={domain}>{domain}</li>)}
                  </ul>
                ) : (
                  <span className="text-faint">—</span>
                ),
            },
            {
              header: 'Servidor',
              cell: (row) => (
                <span className="flex flex-wrap items-center gap-2 whitespace-nowrap">
                  {row.server_name} <ServerStatusBadge status={row.server_status} />
                </span>
              ),
            },
            {
              header: 'Tipo',
              cell: (row) => (
                <div className="num whitespace-nowrap text-sm">
                  {row.type}
                  {row.framework_version && <span className="text-faint"> {row.framework_version}</span>}
                  {row.branch && <div className="text-xs text-muted">rama {row.branch}</div>}
                </div>
              ),
            },
          ]}
        />
      )}
    </Panel>
  )
}
