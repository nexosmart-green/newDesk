import type { LargeFile, LargeFiles, Project } from '../../api/contract'
import { CopyButton } from '../../components/CopyButton'
import { DataTable } from '../../components/DataTable'
import { Panel } from '../../components/Panel'
import { formatBytes, formatDateTime, formatRelative } from '../../lib/format'
import { inventoryProjectFor, lastSegment } from '../../lib/projects'

interface Row extends LargeFile { project: string; projectName: string; absolute: string }

// Los nombres los elige cualquier usuario del server y la ruta se pega en una shell:
// solo se ofrece copiar una ruta sin espacios, comillas ni metacaracteres (NV-07).
const shellSafePath = /^[A-Za-z0-9._@:+/-]+$/

// Archivos pesados de todos los proyectos del server, el más grande primero.
export function LargeFilesPanel({ data, projects }: { data: LargeFiles; projects?: readonly Project[] | null }) {
  const inventory = projects ?? []
  const rows: Row[] = data.projects
    .flatMap((entry) => {
      const projectName = inventoryProjectFor(entry.project, inventory)?.name ?? lastSegment(entry.project)
      return entry.files.map((file) => ({ ...file, project: entry.project, projectName, absolute: `${entry.project}/${file.path}` }))
    })
    .sort((a, b) => b.size_bytes - a.size_bytes)
  const total = data.projects.reduce((sum, entry) => sum + entry.total_bytes, 0)
  const unlisted = data.projects.reduce((sum, entry) => sum + entry.count - entry.files.length, 0)
  const incomplete = data.projects.filter((entry) => entry.truncated || entry.unreadable > 0)
  const threshold = `${Math.round(data.threshold_bytes / 1024 ** 2)} MB` // siempre MB enteros: "50 MB", no "50,0 MB"

  return (
    <Panel
      title={`Archivos pesados · ${rows.length + unlisted}`}
      actions={<span className="num text-xs text-muted">desde {threshold}{total > 0 && ` · ${formatBytes(total)} en total`}</span>}
      bodyClassName=""
    >
      <DataTable<Row>
        noun="archivos"
        rows={rows}
        rowKey={(row) => row.absolute}
        empty={`Ningún archivo de ${threshold} o más en los proyectos.`}
        columns={[
          { header: 'Proyecto', cell: (row) => <span className="font-medium" title={row.project}>{row.projectName}</span> },
          {
            header: 'Archivo',
            // Se ve la ruta relativa; se copia la absoluta.
            cell: (row) =>
              shellSafePath.test(row.absolute) ? (
                <span className="inline-flex max-w-[460px]">
                  <CopyButton variant="chip" text={row.absolute} label={row.path} title={`Copiar ruta: ${row.absolute}`} />
                </span>
              ) : (
                <span className="block max-w-[460px] font-mono text-xs break-all" title={row.absolute}>{row.path}</span>
              ),
          },
          { header: 'Tamaño', align: 'right', cell: (row) => <span className="num whitespace-nowrap">{formatBytes(row.size_bytes)}</span> },
          {
            header: 'Modificado',
            cell: (row) => <span className="num whitespace-nowrap text-xs text-muted" title={formatDateTime(row.modified_at)}>{formatRelative(row.modified_at)}</span>,
          },
        ]}
      />
      {(unlisted > 0 || incomplete.length > 0 || data.truncated) && (
        <ul className="space-y-1 border-t border-line px-5 py-3 text-xs text-muted">
          {unlisted > 0 && <li>{unlisted} archivos pesados más sin listar: el agente manda los 20 más grandes de cada proyecto.</li>}
          {data.truncated && <li className="text-warn">La búsqueda se cortó antes de recorrer todos los proyectos (límite de tiempo o de archivos).</li>}
          {incomplete.map((entry) => (
            <li key={entry.project} className="text-warn">
              <span className="font-mono">{entry.project}</span>:{' '}
              {[entry.truncated && 'búsqueda cortada antes de terminar', entry.unreadable > 0 && `${entry.unreadable} carpetas sin permiso de lectura`].filter(Boolean).join(' · ')}
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
