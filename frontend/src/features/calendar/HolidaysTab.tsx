import { Download } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { PeriodNav } from '@/components/ui/PeriodNav'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import type { Holiday } from '@/features/shifts/types'
import { fieldErrorOf } from '@/lib/api'
import { formatDate } from '@/lib/dates'
import { holidayScopeLabels, type HolidayInput } from './types'
import { useCreateHoliday, useDeleteHoliday, useHolidays, useImportHolidays } from './useCalendar'

const emptyHoliday: HolidayInput = { date: '', name: '', scope: 'company' }

/** RF-06.06 "Configuración → Feriados" (`company.manage`): national holidays and company days off. */
export function HolidaysTab() {
  const [currentYear] = useState(() => new Date().getFullYear())
  const [year, setYear] = useState(currentYear)
  const holidays = useHolidays(year)
  const create = useCreateHoliday()
  const remove = useDeleteHoliday()
  const importing = useImportHolidays()
  // The back imports last year, this one and the next (P-26).
  const importable = Math.abs(year - currentYear) <= 1
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
        <Button type="submit" loading={create.isPending} disabled={!form.date || !form.name.trim()}>
          Agregar
        </Button>
      </form>
      <div className="flex flex-wrap items-center justify-between gap-3">
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
        {importable && (
          <Button variant="secondary" loading={importing.isPending} onClick={() => importing.mutate(year)}>
            <Download aria-hidden="true" className="size-4" strokeWidth={1.75} />
            Importar feriados nacionales de {year}
          </Button>
        )}
      </div>
      {importable && (
        <p className="text-sm text-muted">
          Trae los feriados nacionales del año. Los días que ya tienen un feriado quedan como están. Los días no laborables
          con fines turísticos no se importan: si la empresa los da, cargalos a mano.
        </p>
      )}
      {!holidays.data ? (
        <PageState isPending={holidays.isPending} error={holidays.error} onRetry={() => void holidays.refetch()} />
      ) : holidays.data.length === 0 ? (
        <p className="text-sm text-muted">No hay feriados cargados en {year}.</p>
      ) : (
        <ul aria-label={`Feriados ${year}`} className="divide-y divide-border rounded-lg border border-border bg-surface">
          {holidays.data.map((holiday) => (
            <li key={holiday.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
              <span className="tabular w-20">{formatDate(holiday.date)}</span>
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
          title={`¿Borrar «${deleting.name}»?`}
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(deleting, { onSuccess: closeDelete })}
          onClose={closeDelete}
        >
          <p>El {formatDate(deleting.date)} vuelve a ser un día laborable en los turnos y en los calendarios.</p>
        </ConfirmDialog>
      )}
    </div>
  )
}
