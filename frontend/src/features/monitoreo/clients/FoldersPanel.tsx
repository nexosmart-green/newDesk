import { useId, useMemo, useState } from 'react'
import { Link } from 'react-router'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { useFolderOptions } from '../api/usePortalAdmin'
import { useProjectMutations } from '../api/useProjectMutations'
import type { FolderOption, FolderRef, ProjectAdmin } from '../api/types'
import { Icon } from '../components/Icon'
import { CollapsiblePanel, ErrorNote, Spinner } from '../components/Panel'
import { ServerStatusBadge } from '../components/Status'
import { formatDateTime, formatRelative } from '../lib/format'
import { matchesSearch } from '../lib/projects'

const keyOf = (folder: FolderRef) => `${folder.server_id}:${folder.path}`

const sameSet = (a: ReadonlySet<string>, b: ReadonlySet<string>) => a.size === b.size && [...a].every((key) => b.has(key))

// ---------- carpetas vinculadas (D-044) ----------

// Todas las carpetas del inventario agrupadas por servidor: se tildan las de este proyecto.
// Una carpeta es de un solo proyecto de cliente; las de otros se ven pero no se pueden tildar.
export function FoldersPanel({ project, readOnly }: { project: ProjectAdmin; readOnly: boolean }) {
  const options = useFolderOptions()
  const { saveFolders } = useProjectMutations(project.id)
  const linked = useMemo(() => new Set(project.folders.map(keyOf)), [project.folders])
  // null = sin cambios: se muestra lo guardado.
  const [draft, setDraft] = useState<Set<string> | null>(null)
  const [query, setQuery] = useState('')
  const [onlyLinked, setOnlyLinked] = useState(false)
  const searchId = useId()
  const selected = draft ?? linked
  const dirty = draft !== null && !sameSet(draft, linked)
  const apiError = saveFolders.error instanceof ApiError ? saveFolders.error : null
  const lastUpdates = new Map(project.folders.map((folder) => [keyOf(folder), folder.last_update_at]))

  const groups = useMemo(() => {
    const visible = (options.data ?? []).filter(
      (folder) =>
        (!onlyLinked || selected.has(keyOf(folder))) &&
        (!query.trim() || matchesSearch(query, [folder.name, folder.path, folder.type, folder.server_name, ...folder.domains])),
    )
    const byServer = new Map<number, FolderOption[]>()
    for (const folder of visible) byServer.set(folder.server_id, [...(byServer.get(folder.server_id) ?? []), folder])
    return [...byServer.values()]
      .map((folders) => folders.sort((a, b) => a.name.localeCompare(b.name)))
      .sort((a, b) => (a[0]?.server_name ?? '').localeCompare(b[0]?.server_name ?? ''))
  }, [options.data, query, onlyLinked, selected])

  const toggle = (folder: FolderOption) => {
    const next = new Set(selected)
    if (next.has(keyOf(folder))) next.delete(keyOf(folder))
    else next.add(keyOf(folder))
    setDraft(next)
  }
  const save = () => {
    // Lo vinculado que ya no está en el inventario se conserva tal cual.
    const refs = new Map<string, FolderRef>(project.folders.map((folder) => [keyOf(folder), { server_id: folder.server_id, path: folder.path }]))
    for (const folder of options.data ?? []) refs.set(keyOf(folder), { server_id: folder.server_id, path: folder.path })
    saveFolders.mutate([...selected].flatMap((key) => refs.get(key) ?? []), { onSuccess: () => setDraft(null) })
  }

  // Se consulta poco: arranca plegado y el conteo se ve igual.
  return (
    <CollapsiblePanel title={`Carpetas vinculadas · ${selected.size}`}>
      <p className="px-5 pb-3 text-sm text-muted">
        Las carpetas que el agente encontró en cada servidor. Tildá las de este cliente: con ellas el panel propone qué chequear y el cliente ve la
        fecha de la última actualización. Cada carpeta es de un solo proyecto.
      </p>
      <div className="flex flex-wrap items-center gap-2 px-5 pb-3">
        <label htmlFor={searchId} className="sr-only">Buscar carpeta</label>
        <span className="relative">
          <Icon name="search" size={13} className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-faint" />
          <input
            id={searchId}
            type="search"
            className="input h-8 w-60 pl-8 text-xs"
            placeholder="Buscar carpeta, ruta o dominio…"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
        </span>
        <label className="flex items-center gap-1.5 text-xs text-muted">
          <input type="checkbox" className="size-3.5" checked={onlyLinked} onChange={(event) => setOnlyLinked(event.target.checked)} />
          Solo las tildadas
        </label>
      </div>
      {options.isPending && <div className="px-5 pb-5"><Spinner label="Cargando el inventario…" /></div>}
      {options.isError && <div className="px-5 pb-5"><ErrorNote onRetry={() => void options.refetch()}>{errorMessage(options.error)}</ErrorNote></div>}
      {options.data && (
        <div className="max-h-[28rem] overflow-y-auto border-t border-line">
          {groups.length === 0 && <p className="px-5 py-4 text-sm text-muted">Ninguna carpeta coincide.</p>}
          {groups.map((folders) => {
            const first = folders[0] as FolderOption
            const count = folders.filter((folder) => selected.has(keyOf(folder))).length
            return (
              <section key={first.server_id} aria-label={`Servidor ${first.server_name}`}>
                <h3 className="sticky top-0 z-10 flex items-center gap-2 border-b border-line bg-surface-2/95 px-5 py-2 text-sm font-semibold backdrop-blur">
                  {first.server_name}
                  <ServerStatusBadge status={first.server_status} />
                  <span className="num ml-auto text-xs font-normal text-muted">{count} de {folders.length} tildadas</span>
                </h3>
                <ul className="divide-y divide-line">
                  {folders.map((folder) => (
                    <FolderRow
                      key={keyOf(folder)}
                      folder={folder}
                      projectId={project.id}
                      checked={selected.has(keyOf(folder))}
                      lastUpdate={lastUpdates.get(keyOf(folder))}
                      readOnly={readOnly}
                      onToggle={() => toggle(folder)}
                    />
                  ))}
                </ul>
              </section>
            )
          })}
        </div>
      )}
      {apiError && (
        <div className="px-5 pt-4">
          <ErrorNote>
            {apiError.fieldErrors.folders?.length ? (
              <ul className="space-y-0.5">{apiError.fieldErrors.folders.map((problem) => <li key={problem}>{problem}</li>)}</ul>
            ) : (
              apiError.message
            )}
          </ErrorNote>
        </div>
      )}
      {!readOnly && (
        <div className="flex flex-wrap items-center gap-2 border-t border-line px-5 py-4">
          <button type="button" className="btn btn-primary" disabled={!dirty || saveFolders.isPending} onClick={save}>
            {saveFolders.isPending && <Spinner />}
            Guardar carpetas
          </button>
          {dirty && <button type="button" className="btn btn-ghost" onClick={() => setDraft(null)}>Descartar cambios</button>}
          {saveFolders.isSuccess && !dirty && <span className="fade-in text-sm text-ok">Guardado.</span>}
        </div>
      )}
    </CollapsiblePanel>
  )
}

function FolderRow({ folder, projectId, checked, lastUpdate, readOnly, onToggle }: {
  folder: FolderOption; projectId: number; checked: boolean; lastUpdate: string | null | undefined; readOnly: boolean; onToggle: () => void
}) {
  const foreign = folder.linked !== null && folder.linked.project_id !== projectId ? folder.linked : null
  const id = useId()
  return (
    <li className={`flex items-start gap-3 px-5 py-2.5 ${foreign ? 'opacity-60' : ''}`}>
      <input
        id={id}
        type="checkbox"
        className="mt-1 size-4 shrink-0"
        checked={checked}
        disabled={readOnly || foreign !== null}
        onChange={onToggle}
      />
      <div className="min-w-0 flex-1">
        <label htmlFor={id} className="flex flex-wrap items-baseline gap-x-2 font-medium">
          {folder.name}
          <span className="text-xs font-normal text-muted">
            {folder.type}
            {folder.framework_version && ` ${folder.framework_version}`}
          </span>
        </label>
        <div className="truncate font-mono text-xs text-faint" title={folder.path}>{folder.path}</div>
        <div className="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted">
          {folder.domains.length > 0 && <span>{folder.domains.join(' · ')}</span>}
          {foreign && (
            <span>
              ya es de:{' '}
              <Link className="link" to={`/monitoreo/clientes/${foreign.client_id}/proyectos/${foreign.project_id}`}>
                {foreign.client_name} · {foreign.project_name}
              </Link>
            </span>
          )}
          {lastUpdate !== undefined && (
            <span title={formatDateTime(lastUpdate)}>{lastUpdate ? `Actualizado ${formatRelative(lastUpdate)}` : 'Sin fecha de actualización (sin git)'}</span>
          )}
        </div>
      </div>
    </li>
  )
}
