import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { hasChanges } from '@/lib/formState'
import { workModeLabels, type ShiftTemplate, type ShiftTemplateInput, type WorkMode } from './types'
import { useSaveShiftTemplate } from './useShifts'

const emptyTemplate: ShiftTemplateInput = { name: '', starts_at: '09:00', ends_at: '18:00', color: '#4f8cff', mode: 'office' }

interface TemplateFormDialogProps {
  /** null = new template */
  template: ShiftTemplate | null
  onClose: () => void
}

/** RF-06.01: name, start, end, color and mode. */
export function TemplateFormDialog({ template, onClose }: TemplateFormDialogProps) {
  const save = useSaveShiftTemplate()
  const [initial] = useState<ShiftTemplateInput>(() =>
    template ? { name: template.name, starts_at: template.starts_at, ends_at: template.ends_at, color: template.color, mode: template.mode } : emptyTemplate,
  )
  const [form, setForm] = useState(initial)
  const set = (changes: Partial<ShiftTemplateInput>) => setForm({ ...form, ...changes })

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    save.mutate({ id: template?.id ?? null, input: { ...form, name: form.name.trim() } }, { onSuccess: onClose })
  }

  return (
    <Dialog title={template ? `Editar ${template.name}` : 'Nueva plantilla'} size="md" dirty={hasChanges(initial, form)} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField label="Nombre" required maxLength={60} value={form.name} onChange={(event) => set({ name: event.target.value })} error={fieldErrorOf(save.error, 'name')} />
        <div className="grid grid-cols-2 gap-3">
          <TextField label="Entrada" type="time" required value={form.starts_at} onChange={(event) => set({ starts_at: event.target.value })} error={fieldErrorOf(save.error, 'starts_at')} />
          <TextField label="Salida" type="time" required value={form.ends_at} onChange={(event) => set({ ends_at: event.target.value })} error={fieldErrorOf(save.error, 'ends_at')} />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <SelectField label="Modalidad" value={form.mode} onChange={(event) => set({ mode: event.target.value as WorkMode })}>
            {Object.entries(workModeLabels).map(([mode, label]) => (
              <option key={mode} value={mode}>
                {label}
              </option>
            ))}
          </SelectField>
          <TextField label="Color" type="color" className="[&_input]:h-10 [&_input]:p-1" value={form.color} onChange={(event) => set({ color: event.target.value })} error={fieldErrorOf(save.error, 'color')} />
        </div>
        <p className="text-xs text-muted">Si la salida es anterior a la entrada, el turno termina al día siguiente.</p>
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={!form.name.trim()} loading={save.isPending}>
            {template ? 'Guardar cambios' : 'Crear plantilla'}
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
