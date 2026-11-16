import { CalendarOff, Plus } from 'lucide-react'
import { Link } from 'react-router'
import { Avatar } from '@/components/ui/Avatar'
import { restDayTint, timeRange } from '@/features/shifts/shiftTime'
import { workModeLabels } from '@/features/shifts/types'
import { dayLabel, isWeekend } from '@/lib/week'
import type { TeamCalendar, TeamDay, TeamShift } from './teamCalendarTypes'
import { WorkModeIcon } from './WorkModeIcon'

interface TeamWeekGridProps {
  calendar: TeamCalendar
  /** Company-local today, highlighted. */
  today: string
  /** Who manages shifts gets a "+" in empty days, to the Turnos grid of that week. */
  canAssign?: boolean
}

const shiftText = (shift: TeamShift) =>
  `${shift.template_name ?? 'Turno'} ${timeRange(shift.starts_at, shift.ends_at)} (${workModeLabels[shift.mode]})`

/** "9–18" and the mode icon, whole in a day column; the template's name is the tooltip. */
function ShiftBlock({ shift }: { shift: TeamShift }) {
  return (
    <span
      title={shiftText(shift)}
      className="flex items-center justify-between gap-1.5 rounded-sm border-l-4 bg-bg px-2 py-1.5 text-left"
      style={{ borderLeftColor: shift.color ?? 'var(--color-border)' }}
    >
      <span className="tabular whitespace-nowrap text-sm font-medium">{timeRange(shift.starts_at, shift.ends_at)}</span>
      <WorkModeIcon mode={shift.mode} />
    </span>
  )
}

function HolidayMark({ day }: { day: TeamDay }) {
  if (!day.holiday) return null
  return (
    <span title={`Feriado: ${day.holiday.name}`} className="ml-1.5 inline-flex align-[-2px] text-muted">
      <CalendarOff aria-hidden="true" className="size-3.5" />
      <span className="sr-only">Feriado: {day.holiday.name}</span>
    </span>
  )
}

function shiftsOf(day: TeamDay, userId: number) {
  return day.shifts.filter((shift) => shift.user_id === userId)
}

const isRestDay = (day: TeamDay) => isWeekend(day.date) || day.holiday !== null

/** Today in a light teal; weekends and holidays in a quieter tone. */
function columnTone(day: TeamDay, today: string): string {
  if (day.date === today) return 'bg-brand/5'
  return isRestDay(day) ? restDayTint : ''
}

/** RF-06.05 week: people × days on wide screens, one row per day on a phone. */
export function TeamWeekGrid({ calendar, today, canAssign = false }: TeamWeekGridProps) {
  const nameOf = new Map(calendar.people.map((person) => [person.id, person.full_name]))
  const monday = calendar.days[0]?.date

  return (
    <>
      <div className="hidden overflow-x-auto rounded-lg border border-line bg-surface md:block">
        <table className="w-full min-w-[52rem] table-fixed border-collapse text-sm">
          <caption className="sr-only">Turnos del equipo por persona y día</caption>
          <thead>
            <tr className="border-b border-line">
              <th scope="col" className="w-44 px-3 py-2 text-left font-semibold">
                Persona
              </th>
              {calendar.days.map((day) => (
                <th
                  key={day.date}
                  scope="col"
                  aria-current={day.date === today ? 'date' : undefined}
                  className={`px-1.5 py-2 text-left font-semibold first-letter:uppercase ${
                    day.date === today ? 'bg-brand/10 text-accent' : isRestDay(day) ? `${restDayTint} text-muted` : ''
                  }`}
                >
                  {dayLabel(day.date)}
                  <HolidayMark day={day} />
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {calendar.people.map((person) => (
              <tr key={person.id} className="border-b border-line last:border-b-0">
                <th scope="row" className="px-3 py-1.5 text-left font-medium">
                  <span className="flex items-center gap-2">
                    <Avatar name={person.full_name} url={person.avatar?.sm} size="sm" />
                    <span className="min-w-0 truncate">{person.full_name}</span>
                  </span>
                </th>
                {calendar.days.map((day) => {
                  const shifts = shiftsOf(day, person.id)
                  return (
                    <td key={day.date} className={`group p-1 align-middle ${columnTone(day, today)}`}>
                      {shifts.length > 0 ? (
                        <span className="block space-y-1">
                          {shifts.map((shift) => (
                            <ShiftBlock key={shift.id} shift={shift} />
                          ))}
                        </span>
                      ) : canAssign && monday ? (
                        // Blank until pointed at: a "+" to that week's Turnos grid, only for who can assign.
                        <Link
                          to={`/turnos?semana=${monday}`}
                          aria-label={`Asignar turno a ${person.full_name} el ${dayLabel(day.date)}`}
                          className="flex min-h-10 items-center justify-center rounded-md text-muted opacity-0 transition-opacity hover:bg-surface-2 hover:text-text focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-accent group-hover:opacity-100 coarse:min-h-11 coarse:opacity-60"
                        >
                          <Plus aria-hidden="true" className="size-4" />
                        </Link>
                      ) : (
                        <span className="sr-only">Sin turno</span>
                      )}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <ol className="divide-y divide-line overflow-hidden rounded-lg border border-line bg-surface md:hidden" aria-label="Turnos del equipo por día">
        {calendar.days.map((day) => (
          <li key={day.date} className={`px-4 py-3 ${columnTone(day, today)}`} aria-current={day.date === today ? 'date' : undefined}>
            <h3 className={`flex items-center gap-2 text-sm font-semibold ${isRestDay(day) && day.date !== today ? 'text-muted' : ''}`}>
              <span className="first-letter:uppercase">{dayLabel(day.date)}</span>
              {day.date === today && <span className="text-xs font-normal text-accent">Hoy</span>}
              {day.holiday && (
                <span className="inline-flex min-w-0 items-center gap-1 text-xs font-normal text-muted">
                  <CalendarOff aria-hidden="true" className="size-3.5 shrink-0" />
                  <span className="sr-only">Feriado:</span>
                  <span className="truncate">{day.holiday.name}</span>
                </span>
              )}
            </h3>
            {day.shifts.length === 0 ? (
              <p className="pt-0.5 text-sm text-muted">Nadie tiene turno.</p>
            ) : (
              <ul className="mt-2 space-y-1.5">
                {day.shifts.map((shift) => (
                  <li key={shift.id} className="flex items-center gap-2 text-sm" aria-label={`${nameOf.get(shift.user_id) ?? ''}: ${shiftText(shift)}`}>
                    <span aria-hidden="true" className="h-6 w-1 shrink-0 rounded-full" style={{ backgroundColor: shift.color ?? 'var(--color-border)' }} />
                    <span className="min-w-0 flex-1 truncate font-medium">{nameOf.get(shift.user_id)}</span>
                    <span className="tabular flex shrink-0 items-center gap-1.5 text-muted">
                      {timeRange(shift.starts_at, shift.ends_at)}
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
