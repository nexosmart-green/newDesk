import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import type { Week } from '@/lib/week'
import { fieldErrorOf } from '@/lib/api'
import { TemplateSelect } from './TemplateSelect'
import {
  defaultTemplateValue,
  templateIdOf,
  weekdayOptions,
  type AssignShiftsInput,
  type GridPerson,
  type ShiftTemplate,
} from './types'

interface BulkAssignDialogProps {
  people: readonly GridPerson[]
  templates: readonly ShiftTemplate[]
  week: Week
  pending: boolean
  error: unknown
  onSubmit: (input: AssignShiftsInput) => void
  onClose: () => void
}

function toggle<T>(values: readonly T[], value: T): T[] {
  return values.includes(value) ? values.filter((candidate) => candidate !== value) : [...values, value]
}

/** RF-06.02 bulk: several people × a date range filtered by weekdays (e.g. "lun a vie, Completo"). */
export function BulkAssignDialog({ people, templates, week, pending, error, onSubmit, onClose }: BulkAssignDialogProps) {
  const [userIds, setUserIds] = useState<number[]>([])
  const [range, setRange] = useState({ from: week.from, to: week.to })
  const [weekdays, setWeekdays] = useState<number[]>([1, 2, 3, 4, 5])
  const [template, setTemplate] = useState(() => defaultTemplateValue(templates))
  const ready = userIds.length > 0 && weekdays.length > 0 && range.from !== '' && range.to !== ''

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!ready) return
    const sortedWeekdays = [...weekdays].sort((a, b) => a - b)
    onSubmit({ user_ids: userIds, shift_template_id: templateIdOf(template), ...range, weekdays: sortedWeekdays })
  }

  const allSelected = userIds.length === people.length

  return (
    <Dialog title="Asignación masiva" description="Varias personas, un rango de fechas y los días de la semana que aplican." size="lg" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <fieldset className="space-y-1">
          <legend className="text-sm text-muted">Personas</legend>
          <Button variant="link" onClick={() => setUserIds(allSelected ? [] : people.map((person) => person.id))}>
            {allSelected ? 'Ninguna' : 'Todas'}
          </Button>
          <div className="max-h-56 space-y-1 overflow-y-auto rounded border border-border p-2">
            {people.map((person) => (
              <label key={person.id} className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={userIds.includes(person.id)} onChange={() => setUserIds(toggle(userIds, person.id))} />
                {person.full_name}
              </label>
            ))}
          </div>
        </fieldset>
        <div className="grid grid-cols-2 gap-3">
          <TextField label="Desde" type="date" required value={range.from} onChange={(event) => setRange({ ...range, from: event.target.value })} error={fieldErrorOf(error, 'from')} />
          <TextField label="Hasta" type="date" required value={range.to} onChange={(event) => setRange({ ...range, to: event.target.value })} error={fieldErrorOf(error, 'to')} />
        </div>
        <fieldset>
          <legend className="text-sm text-muted">Días de la semana</legend>
          <div className="flex flex-wrap gap-3 pt-1">
            {weekdayOptions.map((day) => (
              <label key={day.value} className="flex items-center gap-1 text-sm">
                <input type="checkbox" checked={weekdays.includes(day.value)} onChange={() => setWeekdays(toggle(weekdays, day.value))} />
                {day.label}
              </label>
            ))}
          </div>
        </fieldset>
        <TemplateSelect templates={templates} value={template} onChange={setTemplate} />
        <FieldErrorsAlert error={error} />
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={!ready} loading={pending}>
            Asignar
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
