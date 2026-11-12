import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { OptionalDateField } from '@/components/ui/OptionalDateField'
import { SelectField } from '@/components/ui/SelectField'
import { Switch } from '@/components/ui/Switch'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { hasChanges } from '@/lib/formState'
import { WORKING_WEEK, type ShiftRecurrenceInput } from './recurrenceTypes'
import { shiftLabel, workModeLabels, type ShiftTemplate } from './types'
import { useCreateShiftRecurrences } from './useRecurrences'
import { WeekdayPicker } from './Weekdays'

interface RecurrenceFormDialogProps {
  people: readonly { id: number; full_name: string }[]
  templates: readonly ShiftTemplate[]
  /** Company-local `YYYY-MM-DD`: a rule cannot start before it. */
  today: string
  onClose: () => void
}

const SEARCH_FROM = 8

/** RF-06.03 "Nuevo turno fijo": people × template × weekdays from a date, optionally until another. */
export function RecurrenceFormDialog({ people, templates, today, onClose }: RecurrenceFormDialogProps) {
  const create = useCreateShiftRecurrences()
  const [userIds, setUserIds] = useState<number[]>([])
  const [search, setSearch] = useState('')
  const [initial] = useState(() => ({
    templateId: templates[0] ? String(templates[0].id) : '',
    weekdays: [...WORKING_WEEK] as number[],
    startsOn: today,
    endsOn: '',
    skipHolidays: true,
  }))
  const [form, setForm] = useState(initial)
  const set = (changes: Partial<typeof form>) => setForm({ ...form, ...changes })
  const error = create.error
  const ready = userIds.length > 0 && form.templateId !== '' && form.weekdays.length > 0 && form.startsOn !== ''
  const visible = people.filter((person) => person.full_name.toLowerCase().includes(search.trim().toLowerCase()))
  const allSelected = userIds.length === people.length

  function togglePerson(id: number) {
    setUserIds(userIds.includes(id) ? userIds.filter((candidate) => candidate !== id) : [...userIds, id])
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!ready) return
    const input: ShiftRecurrenceInput = {
      user_ids: userIds,
      shift_template_id: Number(form.templateId),
      weekdays: form.weekdays,
      starts_on: form.startsOn,
      ends_on: form.endsOn || null,
      skip_holidays: form.skipHolidays,
    }
    create.mutate(input, { onSuccess: onClose })
  }

  return (
    <Dialog
      title="Nuevo turno fijo"
      description="Se repite solo cada semana. Los días que ya tienen turno no se tocan."
      size="xl"
      dirty={userIds.length > 0 || hasChanges(initial, form)}
      onClose={onClose}
    >
      <form onSubmit={handleSubmit} className="space-y-5">
        {/* People on the left, the rule on the right: the whole form fits 1366×768 without the dialog scrolling. */}
        <div className="grid gap-5 md:grid-cols-2 md:gap-6">
          <fieldset className="space-y-2">
            {/* Floated so the shortcut sits on the legend's line while the legend stays the fieldset's first child. */}
            <legend className="float-left text-sm text-muted">Personas{userIds.length > 0 && ` (${userIds.length})`}</legend>
            <div className="flex justify-end">
              <Button variant="link" onClick={() => setUserIds(allSelected ? [] : people.map((person) => person.id))}>
                {allSelected ? 'Ninguna' : 'Todas'}
              </Button>
            </div>
            {people.length > SEARCH_FROM && (
              <TextField label="Buscar persona" type="search" value={search} onChange={(event) => setSearch(event.target.value)} />
            )}
            <div className="max-h-52 space-y-0.5 overflow-y-auto rounded-lg border border-border p-1.5 md:max-h-[min(20rem,44dvh)]">
              {visible.map((person) => (
                <label
                  key={person.id}
                  className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 text-sm hover:bg-surface-2"
                >
                  <input
                    type="checkbox"
                    className="size-4 accent-brand-strong"
                    checked={userIds.includes(person.id)}
                    onChange={() => togglePerson(person.id)}
                  />
                  {person.full_name}
                </label>
              ))}
              {visible.length === 0 && <p className="px-2 py-3 text-sm text-muted">Nadie coincide con “{search}”.</p>}
            </div>
            {fieldErrorOf(error, 'user_ids') && <p className="text-sm text-danger">{fieldErrorOf(error, 'user_ids')}</p>}
          </fieldset>

          <div className="space-y-5">
            <SelectField
              label="Plantilla"
              required
              value={form.templateId}
              onChange={(event) => set({ templateId: event.target.value })}
              error={fieldErrorOf(error, 'shift_template_id')}
            >
              {templates.map((template) => (
                <option key={template.id} value={String(template.id)}>
                  {shiftLabel(template)} · {workModeLabels[template.mode]}
                </option>
              ))}
            </SelectField>

            <WeekdayPicker value={form.weekdays} onChange={(weekdays) => set({ weekdays })} error={fieldErrorOf(error, 'weekdays')} />

            <div className="grid gap-3 sm:grid-cols-2">
              <TextField
                label="Desde"
                type="date"
                required
                min={today}
                value={form.startsOn}
                onChange={(event) => set({ startsOn: event.target.value })}
                error={fieldErrorOf(error, 'starts_on')}
              />
              <OptionalDateField
                label="Hasta"
                value={form.endsOn}
                onChange={(endsOn) => set({ endsOn })}
                error={fieldErrorOf(error, 'ends_on')}
                hint={form.endsOn ? undefined : 'Sin fecha: sigue hasta que lo finalices.'}
              />
            </div>

            <Switch
              label="Saltear feriados"
              description="Los feriados nacionales y los días no laborables de la empresa quedan sin turno."
              checked={form.skipHolidays}
              onChange={(skipHolidays) => set({ skipHolidays })}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={!ready} loading={create.isPending}>
            Crear turno fijo
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
