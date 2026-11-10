import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { ListSkeleton } from '@/components/ui/Skeleton'
import { TextField } from '@/components/ui/TextField'
import type { TaskLabel } from '@/features/tasks/types'
import { fieldErrorOf } from '@/lib/api'
import { DEFAULT_LABEL_COLOR, tasksCountText } from './catalogFormat'
import { CatalogPanel } from './CatalogPanel'
import { ColorSwatches } from './ColorSwatches'
import { LabelChip } from './LabelChip'
import type { LabelInput } from './taskCatalogsApi'
import { useCreateTaskLabel, useDeleteTaskLabel, useTaskLabels, useUpdateTaskLabel } from './useTaskCatalogs'

/** Configuración → Catálogos: task labels with their color. Deleting one takes it off its tasks. */
export function TaskLabelsPanel() {
  const labels = useTaskLabels()
  const create = useCreateTaskLabel()
  const [formKey, setFormKey] = useState(0)

  return (
    <CatalogPanel title="Etiquetas de tarea" description="Marcas libres con color. Una tarea puede tener varias.">
      {labels.isPending ? (
        <ListSkeleton rows={3} label="Cargando etiquetas…" />
      ) : labels.isError ? (
        <PageState isPending={false} error={labels.error} onRetry={() => void labels.refetch()} />
      ) : labels.data.length === 0 ? (
        <p className="text-sm text-muted">Todavía no hay etiquetas. Creá la primera abajo.</p>
      ) : (
        <ul className="divide-y divide-border" aria-label="Etiquetas de tarea">
          {labels.data.map((label) => (
            <LabelRow key={label.id} label={label} />
          ))}
        </ul>
      )}
      <div className="border-t border-border pt-3">
        <LabelForm
          key={formKey}
          submitLabel="Agregar etiqueta"
          pending={create.isPending}
          error={create.error}
          onSubmit={(input) => create.mutate(input, { onSuccess: () => setFormKey((key) => key + 1) })}
        />
      </div>
    </CatalogPanel>
  )
}

interface LabelFormProps {
  initial?: LabelInput
  submitLabel: string
  pending: boolean
  error: unknown
  onSubmit: (input: LabelInput) => void
  onCancel?: () => void
}

function LabelForm({ initial, submitLabel, pending, error, onSubmit, onCancel }: LabelFormProps) {
  const [name, setName] = useState(initial?.name ?? '')
  const [color, setColor] = useState(initial?.color ?? DEFAULT_LABEL_COLOR)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (name.trim()) onSubmit({ name: name.trim(), color })
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3" noValidate>
      <TextField
        label={initial ? `Nombre de ${initial.name}` : 'Nueva etiqueta'}
        value={name}
        maxLength={50}
        autoFocus={Boolean(initial)}
        onChange={(event) => setName(event.target.value)}
        error={fieldErrorOf(error, 'name')}
      />
      <ColorSwatches value={color} onChange={setColor} error={fieldErrorOf(error, 'color')} />
      <div className="flex flex-wrap items-center justify-between gap-2">
        <LabelChip label={{ name: name.trim() || 'Vista previa', color }} size="md" />
        <div className="flex gap-2">
          {onCancel && (
            <Button variant="ghost" onClick={onCancel}>
              Cancelar
            </Button>
          )}
          <Button type="submit" loading={pending} disabled={!name.trim()}>
            {submitLabel}
          </Button>
        </div>
      </div>
    </form>
  )
}

function LabelRow({ label }: { label: TaskLabel }) {
  const update = useUpdateTaskLabel()
  const remove = useDeleteTaskLabel()
  const [editing, setEditing] = useState(false)
  const [confirming, setConfirming] = useState(false)

  if (editing) {
    return (
      <li className="py-3">
        <LabelForm
          initial={{ name: label.name, color: label.color }}
          submitLabel="Guardar"
          pending={update.isPending}
          error={update.error}
          onSubmit={(input) => update.mutate({ id: label.id, input }, { onSuccess: () => setEditing(false) })}
          onCancel={() => setEditing(false)}
        />
      </li>
    )
  }

  return (
    <li className="flex flex-wrap items-center justify-between gap-2 py-2">
      <div className="flex min-w-0 items-center gap-2">
        <LabelChip label={label} size="md" />
        {label.tasks_count !== undefined && <span className="text-xs text-muted">{tasksCountText(label.tasks_count)}</span>}
      </div>
      <div className="flex gap-1">
        <Button variant="ghost" aria-label={`Editar ${label.name}`} onClick={() => setEditing(true)}>
          Editar
        </Button>
        <Button variant="ghost" className="hover:text-danger" aria-label={`Borrar ${label.name}`} onClick={() => setConfirming(true)}>
          Borrar
        </Button>
      </div>
      {confirming && (
        <ConfirmDialog
          title={`¿Borrar «${label.name}»?`}
          confirmLabel="Borrar etiqueta"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(label.id, { onSuccess: () => setConfirming(false) })}
          onClose={() => setConfirming(false)}
        >
          <p>Se quita de todas las tareas que la tienen. No se puede deshacer.</p>
        </ConfirmDialog>
      )}
    </li>
  )
}
