import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { PageState } from '@/components/ui/PageState'
import { PeriodNav } from '@/components/ui/PeriodNav'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import type { Holiday } from '@/features/shifts/types'
import { fieldErrorOf } from '@/lib/api'
import { formatDate } from '@/lib/dates'
import { holidayScopeLabels, type HolidayInput } from './types'
import { useCreateHoliday, useDeleteHoliday, useHolidays } from './useCalendar'

const emptyHoliday: HolidayInput = { date: '', name: '', scope: 'company' }

/** RF-06.06 "Configuración → Feriados" (`company.manage`): national holidays and company days off. */
export function HolidaysTab() {
  const [currentYear] = useState(() => new Date().getFullYear())
  const [year, setYear] = useState(currentYear)
  const holidays = useHolidays(year)
  const create = useCreateHoliday()
  const remove = useDeleteHoliday()
  const [form, setForm] = useState<HolidayInput>(emptyHoliday)
  const [deleting, setDeleting] = useState<Holiday | null>(null)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    create.mutate({ ...form, name: form.name.trim() }, { onSuccess: () => setForm(emptyHoliday) })
  }

  function closeDelete() {
    remove.reset()
    setDeleting(null)
  }

  return (
    <div className="space-y-4">
      <form onSubmit={handleSubmit} aria-label="Agregar feriado" className="grid gap-3 rounded-lg border border-border bg-surface p-4 sm:grid-cols-[auto_1fr_auto_auto] sm:items-end">
        <TextField label="Fecha" type="date" required value={form.date} onChange={(event) => setForm({ ...form, date: event.target.value })} error={fieldErrorOf(create.error, 'date')} />
        <TextField label="Nombre" required maxLength={120} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} error={fieldErrorOf(create.error, 'name')} />
        <SelectField label="Tipo" value={form.scope} onChange={(event) => setForm({ ...form, scope: event.target.value === 'national' ? 'national' : 'company' })}>
          <option value="company">{holidayScopeLabels.company}</option>
          <option value="national">{holidayScopeLabels.national}</option>
        </SelectField>
        <Button type="submit" disabled={create.isPending || !form.date || !form.name.trim()}>
          Agregar
        </Button>
        <div className="sm:col-span-4">
          <ErrorAlert error={create.error} />
        </div>
      </form>
      <PeriodNav
        label="Elegir año"
        current={String(year)}
        previousLabel="Año anterior"
        todayLabel="Este año"
        nextLabel="Año siguiente"
        isToday={year === currentYear}
        onPrevious={() => setYear(year - 1)}
        onToday={() => setYear(currentYear)}
        onNext={() => setYear(year + 1)}
      />
      {!holidays.data ? (
        <PageState isPending={holidays.isPending} error={holidays.error} />
      ) : holidays.data.length === 0 ? (
        <p className="text-sm text-muted">No hay feriados cargados en {year}.</p>
      ) : (
        <ul aria-label={`Feriados ${year}`} className="divide-y divide-border rounded-lg border border-border bg-surface">
          {holidays.data.map((holiday) => (
            <li key={holiday.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
              <span className="font-mono">{formatDate(holiday.date)}</span>
              <span className="min-w-0 flex-1">{holiday.name}</span>
              <span className="text-xs text-muted">{holidayScopeLabels[holiday.scope]}</span>
              <Button variant="secondary" aria-label={`Borrar ${holiday.name}`} onClick={() => setDeleting(holiday)}>
                Borrar
              </Button>
            </li>
          ))}
        </ul>
      )}
      {deleting && (
        <ConfirmDialog
          title="Borrar feriado"
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(deleting.id, { onSuccess: closeDelete })}
          onClose={closeDelete}
        >
          <p>
            ¿Borrar «{deleting.name}» del {formatDate(deleting.date)}?
          </p>
        </ConfirmDialog>
      )}
    </div>
  )
}
