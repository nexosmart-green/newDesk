import { useState } from 'react'
import { errorMessage } from '../api/monitoringApi'
import { useTargets } from '../api/usePortalAdmin'
import { useProjectMutations } from '../api/useProjectMutations'
import type { ComponentAdmin, ProjectAdmin } from '../api/types'
import { DataTable } from '../components/DataTable'
import { CollapsiblePanel, ErrorNote, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatRelative } from '../lib/format'
import { partsText, PORTAL_PARTS } from '../lib/parts'
import { portalStateMeta } from '../lib/status'
import { ConfirmButton } from '../settings/ConfirmButton'
import { ComponentForm } from './ComponentForm'
import { kindLabels } from './kinds'

function watchedText(item: ComponentAdmin): string {
  if (item.kind === 'http' || item.kind === 'health') return item.url ?? '—'
  return `${item.server_name ?? 'server borrado'} · ${item.target ?? '—'}`
}

export function ComponentsPanel({ project, readOnly }: { project: ProjectAdmin; readOnly: boolean }) {
  const targets = useTargets()
  const { addComponent, updateComponent, removeComponent, checkComponent, measurePageSpeed } = useProjectMutations(project.id)
  // null = cerrado, 'new' = alta, número = edición de ese componente.
  const [editing, setEditing] = useState<'new' | number | null>(null)
  const components = [...project.components].sort((a, b) => a.position - b.position || a.id - b.id)
  const current = typeof editing === 'number' ? components.find((item) => item.id === editing) : undefined
  const mutation = editing === 'new' ? addComponent : updateComponent
  const projectParts = PORTAL_PARTS.filter((part) => components.some((item) => item.id !== current?.id && item.parts.includes(part)))

  return (
    <CollapsiblePanel title={`Componentes · ${components.length}`}>
      {!readOnly && editing === null && (
        <div className="px-5 pb-3">
          <button type="button" className="btn btn-primary btn-sm" onClick={() => { addComponent.reset(); setEditing('new') }}>Agregar componente</button>
        </div>
      )}
      {editing !== null && (
        <div className="fade-in border-b border-line px-5 pb-5">
          {targets.isError && <div className="mb-3"><ErrorNote>{errorMessage(targets.error)}</ErrorNote></div>}
          <ComponentForm
            key={String(editing)}
            initial={current}
            projectParts={projectParts}
            targets={targets.data ?? []}
            pending={mutation.isPending}
            error={mutation.error}
            onCancel={() => setEditing(null)}
            onSubmit={(input) => {
              const done = { onSuccess: () => setEditing(null) }
              if (editing === 'new') addComponent.mutate(input, done)
              else updateComponent.mutate({ id: editing, input }, done)
            }}
          />
        </div>
      )}
      <DataTable<ComponentAdmin>
        noun="componentes"
        rows={components}
        rowKey={(row) => String(row.id)}
        empty="Sin componentes: agregá el sitio, la API, los workers o el backup que el cliente tiene que ver."
        columns={[
          {
            header: 'Componente',
            cell: (row) => (
              <div>
                <div className="font-medium">{row.label}</div>
                <div className="text-xs text-muted">{kindLabels[row.kind]}</div>
              </div>
            ),
          },
          { header: 'Qué mira', cell: (row) => <span className="block max-w-[280px] truncate font-mono text-xs text-muted" title={watchedText(row)}>{watchedText(row)}</span> },
          {
            header: 'Afecta a',
            cell: (row) =>
              row.parts.length > 0 ? (
                <span className="text-sm whitespace-nowrap">{partsText(row.parts)}</span>
              ) : (
                <span className="text-xs text-muted">{row.kind === 'timer' ? 'Tarjeta de backups' : 'Solo el equipo'}</span>
              ),
          },
          {
            header: 'Estado',
            cell: (row) => (
              <div className="space-y-1">
                <Badge tone={portalStateMeta[row.status].tone}>{portalStateMeta[row.status].label}</Badge>
                {row.detail && <div className="text-xs text-muted">{row.detail}</div>}
              </div>
            ),
          },
          {
            header: 'Último chequeo',
            cell: (row) => (
              <span className="num text-xs whitespace-nowrap text-muted">
                {formatRelative(row.last_checked_at)}
                {row.latency_ms !== null && ` · ${row.latency_ms} ms`}
              </span>
            ),
          },
          ...(readOnly
            ? []
            : [{
                header: 'Acciones',
                cell: (row: ComponentAdmin) => (
                  <div className="flex flex-wrap gap-1.5">
                    <button type="button" className="btn btn-sm" onClick={() => { updateComponent.reset(); setEditing(row.id) }}>Editar</button>
                    <button
                      type="button"
                      className="btn btn-sm"
                      disabled={checkComponent.isPending && checkComponent.variables === row.id}
                      onClick={() => checkComponent.mutate(row.id)}
                    >
                      {checkComponent.isPending && checkComponent.variables === row.id && <Spinner />}
                      Chequear ahora
                    </button>
                    {row.kind === 'http' && (
                      <button
                        type="button"
                        className="btn btn-sm"
                        disabled={measurePageSpeed.isPending && measurePageSpeed.variables === row.id}
                        onClick={() => measurePageSpeed.mutate(row.id)}
                      >
                        Medir PageSpeed
                      </button>
                    )}
                    <ConfirmButton label="Eliminar" confirmLabel="Confirmar" onConfirm={() => removeComponent.mutate(row.id)} />
                  </div>
                ),
              }]),
        ]}
      />
      {checkComponent.isError && <div className="px-5 pb-4"><ErrorNote>{errorMessage(checkComponent.error)}</ErrorNote></div>}
      {measurePageSpeed.isError && <div className="px-5 pb-4"><ErrorNote>{errorMessage(measurePageSpeed.error)}</ErrorNote></div>}
      {measurePageSpeed.isSuccess && (
        <p role="status" className="fade-in px-5 pb-4 text-sm text-muted">Midiendo: el resultado aparece en un par de minutos.</p>
      )}
    </CollapsiblePanel>
  )
}
