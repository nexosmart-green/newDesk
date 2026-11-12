import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { TemplateFormDialog } from './TemplateFormDialog'
import { workModeLabels, type ShiftTemplate } from './types'
import { useDeleteShiftTemplate, useShiftTemplates } from './useShifts'

type Editing = { template: ShiftTemplate | null } | null

/** RF-06.01: the templates the grid assigns. Deleting one keeps the days already assigned. */
export function ShiftTemplatesPanel() {
  const templates = useShiftTemplates()
  const remove = useDeleteShiftTemplate()
  const [editing, setEditing] = useState<Editing>(null)
  const [deleting, setDeleting] = useState<ShiftTemplate | null>(null)

  function closeDelete() {
    remove.reset()
    setDeleting(null)
  }

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <Button onClick={() => setEditing({ template: null })}>Nueva plantilla</Button>
      </div>
      {!templates.data ? (
        <PageState isPending={templates.isPending} error={templates.error} shape="list" onRetry={() => void templates.refetch()} />
      ) : templates.data.length === 0 ? (
        <p className="text-sm text-muted">Todavía no hay plantillas. Creá una (ej.: "Completo 09:00–18:00").</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Plantillas de turno">
          {templates.data.map((template) => (
            <li key={template.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
              <span aria-hidden className="h-4 w-4 shrink-0 rounded-full" style={{ backgroundColor: template.color }} />
              <span className="min-w-0 flex-1">
                <span className="font-medium">{template.name}</span>{' '}
                <span className="text-muted">
                  {template.starts_at}–{template.ends_at} · {workModeLabels[template.mode]}
                </span>
              </span>
              <Button variant="secondary" aria-label={`Editar ${template.name}`} onClick={() => setEditing({ template })}>
                Editar
              </Button>
              <Button variant="secondary" aria-label={`Borrar ${template.name}`} onClick={() => setDeleting(template)}>
                Borrar
              </Button>
            </li>
          ))}
        </ul>
      )}
      {editing && <TemplateFormDialog template={editing.template} onClose={() => setEditing(null)} />}
      {deleting && (
        <ConfirmDialog
          title={`¿Borrar «${deleting.name}»?`}
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(deleting, { onSuccess: closeDelete })}
          onClose={closeDelete}
        >
          <p>Deja de aparecer para asignar; los días ya asignados con esta plantilla conservan su horario.</p>
        </ConfirmDialog>
      )}
    </div>
  )
}
