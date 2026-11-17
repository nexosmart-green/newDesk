import { useId, useState } from 'react'
import type { Project, Supervisor, SupervisorProgram } from '../../api/contract'
import { DataTable } from '../../components/DataTable'
import { Icon } from '../../components/Icon'
import { Empty, Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'
import { isParked, processTone } from '../../lib/status'
import { formatDuration } from '../../lib/format'
import { inventoryProjectFor, lastSegment } from '../../lib/projects'

const NO_PROJECT = '-'

interface ProjectOption { key: string; label: string; path: string; count: number }

// Proyecto de un programa para el filtro: el del inventario que lo contiene o, si
// el inventario no lo tiene, la carpeta que mandó el agente.
function programProject(program: SupervisorProgram, projects: readonly Project[]): Omit<ProjectOption, 'count'> {
  if (!program.project) return { key: NO_PROJECT, label: 'Sin proyecto', path: '' }
  const match = inventoryProjectFor(program.project, projects)
  return match ? { key: match.path, label: match.name, path: match.path } : { key: program.project, label: lastSegment(program.project), path: program.project }
}

function projectOptions(programs: readonly SupervisorProgram[], projects: readonly Project[]): ProjectOption[] {
  if (!programs.some((program) => program.project)) return [] // agente viejo: no hay con qué filtrar
  const options = new Map<string, ProjectOption>()
  for (const program of programs) {
    const project = programProject(program, projects)
    const option = options.get(project.key)
    if (option) option.count++
    else options.set(project.key, { ...project, count: 1 })
  }
  return [...options.values()].sort((a, b) => Number(a.key === NO_PROJECT) - Number(b.key === NO_PROJECT) || a.label.localeCompare(b.label))
}

export function SupervisorPanel({ data, projects }: { data: Supervisor; projects?: readonly Project[] | null }) {
  const [filter, setFilter] = useState('')
  const filterId = useId()
  if (!data.available) return <Empty>Supervisor no está instalado o el agente no puede consultarlo.</Empty>
  const inventory = projects ?? []
  const all = [...(data.programs ?? [])].sort((a, b) => Number(a.healthy) - Number(b.healthy) || a.name.localeCompare(b.name))
  const options = projectOptions(all, inventory)
  // Si el proyecto elegido desaparece en un refresco, se vuelve a "Todos".
  const active = options.some((option) => option.key === filter) ? filter : ''
  const programs = active ? all.filter((program) => programProject(program, inventory).key === active) : all
  const bad = programs.filter((p) => !p.healthy).length
  const parked = programs.filter(isParked).length

  return (
    <Panel
      title={active ? `Supervisor · ${programs.length} de ${all.length} programas` : `Supervisor · ${all.length} programas`}
      actions={
        <span className="flex flex-wrap items-center gap-2">
          {options.length > 0 && (
            <span className="relative">
              <label htmlFor={filterId} className="sr-only">Filtrar por proyecto</label>
              <select id={filterId} className="input h-8 w-auto max-w-56 text-xs" value={active} onChange={(event) => setFilter(event.target.value)}>
                <option value="">Todos los proyectos</option>
                {options.map((option) => (
                  <option key={option.key} value={option.key} title={option.path || undefined}>
                    {option.label} ({option.count})
                  </option>
                ))}
              </select>
              <Icon name="chevron" size={10} className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rotate-90 text-muted" />
            </span>
          )}
          {parked > 0 && <Badge tone="idle">{parked} detenidos a propósito</Badge>}
          {bad > 0 ? <Badge tone="crit">{bad} con problemas</Badge> : <Badge tone="ok">todo en orden</Badge>}
        </span>
      }
      bodyClassName=""
    >
      <DataTable<SupervisorProgram>
        key={active} // usePage no vuelve solo a la página 1 cuando cambia el filtro
        noun="programas"
        rows={programs}
        rowKey={(row) => row.name}
        rowClassName={(row) => (row.healthy ? '' : 'bg-crit/5')}
        columns={[
          {
            header: 'Programa',
            cell: (row) => {
              const where = row.directory ?? row.project
              return (
                <div className="font-mono text-xs">
                  {row.group && row.name.startsWith(`${row.group}:`) ? (
                    <>
                      <span className="text-faint">{row.group}:</span>
                      {row.name.slice(row.group.length + 1)}
                    </>
                  ) : (
                    row.name
                  )}
                  {where && (
                    <div className="max-w-[320px] truncate text-faint" title={row.directory ? `directory=${row.directory}` : `proyecto ${row.project}`}>
                      {where}
                    </div>
                  )}
                </div>
              )
            },
          },
          { header: 'Estado', cell: (row) => <SupervisorState program={row} /> },
          { header: 'Autostart', cell: (row) => <span className="num text-xs text-muted">{row.autostart === undefined ? '—' : row.autostart ? 'sí' : 'no'}</span> },
          { header: 'PID', align: 'right', cell: (row) => <span className="num text-muted">{row.pid ?? '—'}</span> },
          { header: 'Uptime', align: 'right', cell: (row) => <span className="num whitespace-nowrap">{formatDuration(row.uptime_seconds)}</span> },
          { header: 'Detalle', cell: (row) => <span className="text-xs text-muted">{row.description ?? ''}</span> },
        ]}
      />
    </Panel>
  )
}

function SupervisorState({ program }: { program: SupervisorProgram }) {
  if (isParked(program)) {
    return (
      <span title="autostart=false en la config de Supervisor">
        <Badge tone="idle">detenido a propósito</Badge>
      </span>
    )
  }
  // El veredicto es del agente: STOPPED con autostart=true es una falla aunque el tono base sea neutro.
  return <Badge tone={program.healthy ? processTone(program.state) : 'crit'}>{program.state}</Badge>
}
