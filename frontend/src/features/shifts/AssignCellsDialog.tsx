import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TemplateSelect } from './TemplateSelect'
import { defaultTemplateValue, templateIdOf, type ShiftTemplate } from './types'

interface AssignCellsDialogProps {
  cellCount: number
  templates: readonly ShiftTemplate[]
  pending: boolean
  error: unknown
  onSubmit: (templateId: number | null) => void
  onClose: () => void
}

/** Assigns one template (or clears) every selected grid cell. */
export function AssignCellsDialog({ cellCount, templates, pending, error, onSubmit, onClose }: AssignCellsDialogProps) {
  const [value, setValue] = useState(() => defaultTemplateValue(templates))

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    onSubmit(templateIdOf(value))
  }

  return (
    <Dialog title={cellCount === 1 ? 'Asignar turno' : `Asignar turno a ${cellCount} días`} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <TemplateSelect templates={templates} value={value} onChange={setValue} />
        <p className="text-xs text-muted">Reemplaza el turno que ya tuvieran esos días.</p>
        <ErrorAlert error={error} showFieldErrors />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={pending}>
            {pending ? 'Guardando…' : 'Aplicar'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
