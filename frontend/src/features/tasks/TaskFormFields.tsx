import { Disclosure } from '@/components/ui/Disclosure'
import { OptionalDateField } from '@/components/ui/OptionalDateField'
import { SelectField } from '@/components/ui/SelectField'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { formatDate } from '@/lib/dates'
import type { TaskFormValues } from './taskForm'
import { isTaskPriority, taskPriorities, taskPriorityLabels } from './types'

interface TaskFormFieldsProps {
  values: TaskFormValues
  error: unknown
  onChange: (changes: Partial<TaskFormValues>) => void
  /** "Más opciones" starts open (editing a task that already has dates/estimate). */
  optionsOpen?: boolean
}

const OPTIONAL_FIELDS = ['starts_on', 'due_on', 'estimate_seconds'] as const

/** What the closed "Más opciones" holds, so nothing set is ever hidden silently. */
function optionsSummary(values: TaskFormValues): string {
  const parts = [
    values.starts_on && `Inicia ${formatDate(values.starts_on)}`,
    values.due_on && `Vence ${formatDate(values.due_on)}`,
    values.estimate_hours.trim() && `${values.estimate_hours.trim()} h estimadas`,
  ].filter(Boolean)
  return parts.length > 0 ? parts.join(', ') : 'Fechas y estimación (opcionales)'
}

/** RF-04.01 fields shared by "Nueva tarea" and the task page. Dates and estimate are optional. */
export function TaskFormFields({ values, error, onChange, optionsOpen = false }: TaskFormFieldsProps) {
  const optionalError = OPTIONAL_FIELDS.some((field) => fieldErrorOf(error, field))

  return (
    <>
      <TextField
        label="Título"
        required
        maxLength={255}
        value={values.title}
        onChange={(event) => onChange({ title: event.target.value })}
        error={fieldErrorOf(error, 'title')}
      />
      <TextAreaField
        label="Descripción"
        rows={4}
        value={values.description}
        onChange={(event) => onChange({ description: event.target.value })}
        error={fieldErrorOf(error, 'description')}
      />
      <SelectField
        label="Prioridad"
        value={values.priority}
        className="sm:max-w-60"
        onChange={(event) => {
          if (isTaskPriority(event.target.value)) onChange({ priority: event.target.value })
        }}
      >
        {taskPriorities.map((priority) => (
          <option key={priority} value={priority}>
            {taskPriorityLabels[priority]}
          </option>
        ))}
      </SelectField>
      {/* A validation error inside forces it open: the message must be visible. */}
      <Disclosure
        key={optionalError ? 'error' : 'ok'}
        label="Más opciones"
        summary={optionsSummary(values)}
        defaultOpen={optionsOpen || optionalError}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <OptionalDateField
            label="Inicio"
            value={values.starts_on}
            onChange={(starts_on) => onChange({ starts_on })}
            error={fieldErrorOf(error, 'starts_on')}
          />
          <OptionalDateField
            label="Vencimiento"
            value={values.due_on}
            onChange={(due_on) => onChange({ due_on })}
            error={fieldErrorOf(error, 'due_on')}
          />
        </div>
        <TextField
          label="Estimación en horas (opcional)"
          inputMode="decimal"
          placeholder="Ej.: 1,5"
          className="sm:max-w-60"
          value={values.estimate_hours}
          onChange={(event) => onChange({ estimate_hours: event.target.value })}
          error={fieldErrorOf(error, 'estimate_seconds')}
          hint="Dejalo vacío si no hace falta."
        />
      </Disclosure>
    </>
  )
}
