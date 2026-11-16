import { CalendarOff, Plus } from 'lucide-react'
import { WorkModeIcon } from '@/features/calendar/WorkModeIcon'
import { dayLabel, isWeekend } from '@/lib/week'
import { cellKey, type Cell } from './selection'
import { restDayTint, timeRange } from './shiftTime'
import { shiftLabel, type Holiday, type Shift, type ShiftGrid } from './types'

interface ShiftGridTableProps {
  grid: ShiftGrid
  dates: readonly string[]
  selected: ReadonlySet<string>
  onToggle: (cell: Cell) => void
}

/** "9–18" and the mode icon: fits a day column whole; the template's name is in the tooltip. */
function ShiftChip({ shift }: { shift: Shift }) {
  return (
    <span
      title={`${shift.name ?? 'Turno'} ${timeRange(shift.starts_at, shift.ends_at)}, ${shift.mode_label.toLowerCase()}`}
      className="flex w-full items-center justify-between gap-1.5 rounded-sm border-l-4 bg-bg px-2 py-1.5 text-left"
      style={{ borderLeftColor: shift.color ?? 'var(--color-border)' }}
    >
      <span className="tabular whitespace-nowrap font-medium">{timeRange(shift.starts_at, shift.ends_at)}</span>
      <WorkModeIcon mode={shift.mode} />
    </span>
  )
}

function HolidayMark({ holidays }: { holidays: readonly Holiday[] }) {
  if (holidays.length === 0) return null
  const names = holidays.map((holiday) => holiday.name).join(', ')
  return (
    <span title={`Feriado: ${names}`} className="ml-1.5 inline-flex align-[-2px] text-muted">
      <CalendarOff aria-hidden="true" className="size-3.5" />
      <span className="sr-only">Feriado: {names}</span>
    </span>
  )
}

/** RF-06.02: people × days. Each cell is a toggle so several can be assigned at once. */
export function ShiftGridTable({ grid, dates, selected, onToggle }: ShiftGridTableProps) {
  const holidaysOn = (date: string) => grid.holidays.filter((holiday) => holiday.date === date)
  const isRestDay = (date: string) => isWeekend(date) || holidaysOn(date).length > 0

  return (
    // The grid scrolls inside its own box: the page never scrolls sideways on a phone.
    <div className="overflow-x-auto rounded-lg border border-line bg-surface">
      <table className="w-full min-w-[52rem] table-fixed border-collapse text-sm">
        <caption className="sr-only">Turnos de la semana por persona</caption>
        <thead>
          <tr className="border-b border-line">
            <th scope="col" className="w-44 px-3 py-2 text-left font-semibold">
              Persona
            </th>
            {dates.map((date) => (
              <th
                key={date}
                scope="col"
                className={`px-1.5 py-2 text-left font-semibold first-letter:uppercase ${isRestDay(date) ? `${restDayTint} text-muted` : ''}`}
              >
                {dayLabel(date)}
                <HolidayMark holidays={holidaysOn(date)} />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {grid.people.map((person) => (
            <tr key={person.id} className="border-b border-line last:border-b-0">
              <th scope="row" className="truncate px-3 py-1 text-left font-medium">
                {person.full_name}
              </th>
              {dates.map((date) => {
                const shift = person.shifts.find((candidate) => candidate.date === date)
                const isSelected = selected.has(cellKey({ userId: person.id, date }))
                return (
                  <td key={date} className={`p-1 align-middle ${isRestDay(date) ? restDayTint : ''}`}>
                    <button
                      type="button"
                      aria-pressed={isSelected}
                      aria-label={`${person.full_name}, ${dayLabel(date)}: ${shift ? `${shiftLabel(shift)} (${shift.mode_label})` : 'sin turno'}`}
                      onClick={() => onToggle({ userId: person.id, date })}
                      className={`group flex min-h-10 w-full cursor-pointer items-center justify-center rounded-md p-0.5 focus-visible:outline-2 focus-visible:outline-accent coarse:min-h-11 ${isSelected ? 'bg-accent/15 outline-2 outline-accent' : 'hover:bg-surface-2'}`}
                    >
                      {shift ? (
                        <ShiftChip shift={shift} />
                      ) : (
                        // Blank until pointed at: the + says "you can assign here", without a dash in every empty day.
                        <Plus
                          aria-hidden="true"
                          data-testid="assign-affordance"
                          className={`size-4 text-muted transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 ${isSelected ? 'opacity-100' : 'opacity-0'}`}
                        />
                      )}
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
