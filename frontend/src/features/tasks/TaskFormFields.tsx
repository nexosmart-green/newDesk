import { SelectField } from '@/components/ui/SelectField'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { TaskFormValues } from './taskForm'
import { isTaskPriority, taskPriorities, taskPriorityLabels } from './types'

interface TaskFormFieldsProps {
  values: TaskFormValues
  error: unknown
  onChange: (changes: Partial<TaskFormValues>) => void
}

/** RF-04.01 fields shared by "Nueva tarea" and the task page. */
export function TaskFormFields({ values, error, onChange }: TaskFormFieldsProps) {
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
      <div className="grid gap-3 sm:grid-cols-2">
        <SelectField
          label="Prioridad"
          value={values.priority}
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
        <TextField
          label="Estimación (horas)"
          type="number"
          min={0}
          step={0.25}
          inputMode="decimal"
          value={values.estimate_hours}
          onChange={(event) => onChange({ estimate_hours: event.target.value })}
          error={fieldErrorOf(error, 'estimate_seconds')}
        />
        <TextField
          label="Inicio"
          type="date"
          value={values.starts_on}
          onChange={(event) => onChange({ starts_on: event.target.value })}
          error={fieldErrorOf(error, 'starts_on')}
        />
        <TextField
          label="Vencimiento"
          type="date"
          value={values.due_on}
          onChange={(event) => onChange({ due_on: event.target.value })}
          error={fieldErrorOf(error, 'due_on')}
        />
      </div>
    </>
  )
}
