import { Avatar } from '@/components/ui/Avatar'
import { workModeLabels } from '@/features/shifts/types'
import { dayLabel } from '@/features/timesheet/week'
import type { TeamCalendar, TeamDay, TeamShift } from './teamCalendarTypes'
import { WorkModeIcon } from './WorkModeIcon'

interface TeamWeekGridProps {
  calendar: TeamCalendar
  /** Company-local today, highlighted. */
  today: string
}

const shiftText = (shift: TeamShift) => `${shift.template_name ?? 'Turno'} ${shift.starts_at}–${shift.ends_at} (${workModeLabels[shift.mode]})`

function ShiftBlock({ shift }: { shift: TeamShift }) {
  return (
    <span className="block rounded-md border-l-4 bg-bg px-2 py-1 text-left" style={{ borderLeftColor: shift.color ?? 'var(--color-border)' }}>
      <span className="block truncate text-xs font-medium">{shift.template_name ?? 'Turno'}</span>
      <span className="tabular flex items-center gap-1.5 text-xs text-muted">
        {shift.starts_at}–{shift.ends_at}
        <WorkModeIcon mode={shift.mode} />
      </span>
    </span>
  )
}

function shiftsOf(day: TeamDay, userId: number) {
  return day.shifts.filter((shift) => shift.user_id === userId)
}

/** RF-06.05 week: people × days on wide screens, one list per day on a phone. */
export function TeamWeekGrid({ calendar, today }: TeamWeekGridProps) {
  const nameOf = new Map(calendar.people.map((person) => [person.id, person.full_name]))

  return (
    <>
      <div className="hidden overflow-x-auto rounded-xl border border-border bg-surface md:block">
        <table className="w-full min-w-[52rem] table-fixed border-collapse text-sm">
          <caption className="sr-only">Turnos del equipo por persona y día</caption>
          <thead>
            <tr className="border-b border-border">
              <th scope="col" className="w-44 px-3 py-2 text-left font-semibold">
                Persona
              </th>
              {calendar.days.map((day) => (
                <th
                  key={day.date}
                  scope="col"
                  aria-current={day.date === today ? 'date' : undefined}
                  className={`px-1.5 py-2 text-left align-top font-semibold capitalize ${day.date === today ? 'bg-brand/10 text-brand-strong' : ''}`}
                >
                  {dayLabel(day.date)}
                  {day.holiday && <span className="block text-xs font-normal normal-case text-warning">Feriado: {day.holiday.name}</span>}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {calendar.people.map((person) => (
              <tr key={person.id} className="border-b border-border last:border-b-0">
                <th scope="row" className="px-3 py-1.5 text-left font-medium">
                  <span className="flex items-center gap-2">
                    <Avatar name={person.full_name} url={person.avatar?.sm} size="sm" />
                    <span className="min-w-0 truncate">{person.full_name}</span>
                  </span>
                </th>
                {calendar.days.map((day) => {
                  const shifts = shiftsOf(day, person.id)
                  return (
                    <td key={day.date} className={`p-1 align-top ${day.date === today ? 'bg-brand/5' : ''}`}>
                      {shifts.length === 0 ? (
                        <span className="relative block py-2 text-center text-muted">
                          <span aria-hidden="true">—</span>
                          <span className="sr-only">Sin turno</span>
                        </span>
                      ) : (
                        <span className="block space-y-1">
                          {shifts.map((shift) => (
                            <ShiftBlock key={shift.id} shift={shift} />
                          ))}
                        </span>
                      )}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ol className="space-y-3 md:hidden" aria-label="Turnos del equipo por día">
        {calendar.days.map((day) => (
          <li key={day.date} className={`rounded-xl border bg-surface p-3 ${day.date === today ? 'border-brand/60' : 'border-border'}`}>
            <h3 className="text-sm font-semibold capitalize">
              {dayLabel(day.date)}
              {day.date === today && <span className="ml-2 text-xs font-normal normal-case text-brand-strong">Hoy</span>}
            </h3>
            {day.holiday && <p className="text-xs text-warning">Feriado: {day.holiday.name}</p>}
            {day.shifts.length === 0 ? (
              <p className="pt-1 text-sm text-muted">Nadie tiene turno.</p>
            ) : (
              <ul className="mt-2 space-y-1.5">
                {day.shifts.map((shift) => (
                  <li key={shift.id} className="flex items-center gap-2 text-sm" aria-label={`${nameOf.get(shift.user_id) ?? ''}: ${shiftText(shift)}`}>
                    <span aria-hidden="true" className="h-8 w-1 shrink-0 rounded-full" style={{ backgroundColor: shift.color ?? 'var(--color-border)' }} />
                    <span className="min-w-0 flex-1 truncate font-medium">{nameOf.get(shift.user_id)}</span>
                    <span className="tabular flex shrink-0 items-center gap-1.5 text-xs text-muted">
                      {shift.starts_at}–{shift.ends_at}
                      <WorkModeIcon mode={shift.mode} />
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </li>
        ))}
      </ol>
    </>
  )
}
