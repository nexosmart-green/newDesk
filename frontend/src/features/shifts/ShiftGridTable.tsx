import { dayLabel } from '@/lib/week'
import { cellKey, type Cell } from './selection'
import { shiftLabel, type Holiday, type Shift, type ShiftGrid } from './types'

interface ShiftGridTableProps {
  grid: ShiftGrid
  dates: readonly string[]
  selected: ReadonlySet<string>
  onToggle: (cell: Cell) => void
}

function ShiftChip({ shift }: { shift: Shift }) {
  return (
    <span
      className="block space-y-0.5 rounded border-l-4 bg-bg px-2 py-1 text-left"
      style={{ borderLeftColor: shift.color ?? 'var(--color-border)' }}
    >
      <span className="block truncate font-medium">{shift.name ?? 'Turno'}</span>
      <span className="block text-xs text-muted">
        {shift.starts_at}–{shift.ends_at} · {shift.mode_label}
      </span>
    </span>
  )
}

function HolidayNames({ holidays }: { holidays: readonly Holiday[] }) {
  if (holidays.length === 0) return null
  return (
    <span className="mt-1 block text-xs font-normal text-warning">
      {holidays.map((holiday) => `Feriado: ${holiday.name}`).join(' · ')}
    </span>
  )
}

/** RF-06.02: people × days. Each cell is a toggle so several can be assigned at once. */
export function ShiftGridTable({ grid, dates, selected, onToggle }: ShiftGridTableProps) {
  const holidaysOn = (date: string) => grid.holidays.filter((holiday) => holiday.date === date)

  return (
    // The grid scrolls inside its own box: the page never scrolls sideways on a phone.
    <div className="overflow-x-auto rounded-lg border border-border bg-surface">
      <table className="w-full min-w-[56rem] table-fixed border-collapse text-sm">
        <caption className="sr-only">Turnos de la semana por persona</caption>
        <thead>
          <tr className="border-b border-border">
            <th scope="col" className="w-40 px-2 py-2 text-left font-semibold">
              Persona
            </th>
            {dates.map((date) => (
              <th key={date} scope="col" className="px-1 py-2 text-left align-top font-semibold capitalize">
                {dayLabel(date)}
                <HolidayNames holidays={holidaysOn(date)} />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {grid.people.map((person) => (
            <tr key={person.id} className="border-b border-border last:border-b-0">
              <th scope="row" className="truncate px-2 py-1 text-left font-medium">
                {person.full_name}
              </th>
              {dates.map((date) => {
                const shift = person.shifts.find((candidate) => candidate.date === date)
                const isSelected = selected.has(cellKey({ userId: person.id, date }))
                return (
                  <td key={date} className="p-1 align-top">
                    <button
                      type="button"
                      aria-pressed={isSelected}
                      aria-label={`${person.full_name}, ${dayLabel(date)}: ${shift ? `${shiftLabel(shift)} (${shift.mode_label})` : 'sin turno'}`}
                      onClick={() => onToggle({ userId: person.id, date })}
                      className={`block min-h-12 w-full rounded p-0.5 ${isSelected ? 'outline-2 outline-accent' : 'hover:bg-bg'}`}
                    >
                      {shift ? <ShiftChip shift={shift} /> : <span className="block py-2 text-center text-muted">—</span>}
                    </button>
                  </td>
                )
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
