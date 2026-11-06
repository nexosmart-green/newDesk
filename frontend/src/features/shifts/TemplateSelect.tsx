import { SelectField } from '@/components/ui/SelectField'
import { CLEAR_SHIFT, shiftLabel, workModeLabels, type ShiftTemplate } from './types'

interface TemplateSelectProps {
  templates: readonly ShiftTemplate[]
  value: string
  onChange: (value: string) => void
}

/** Pick a template, or "Sin turno" to clear (sent as `shift_template_id: null`). */
export function TemplateSelect({ templates, value, onChange }: TemplateSelectProps) {
  return (
    <SelectField label="Turno" value={value} onChange={(event) => onChange(event.target.value)}>
      {templates.map((template) => (
        <option key={template.id} value={String(template.id)}>
          {shiftLabel(template)} · {workModeLabels[template.mode]}
        </option>
      ))}
      <option value={CLEAR_SHIFT}>Sin turno (quitar)</option>
    </SelectField>
  )
}
