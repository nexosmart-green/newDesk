import { parseLocalDate, toLocalDateString } from '@/features/timesheet/week'
import { zonedPartsOf } from '@/lib/timezone'
import type { TeamCalendar, TeamDay, TeamPerson, TeamShift } from './teamCalendarTypes'

/** "Quién está hoy": where each person stands right now, by the company's wall clock. */
export type Presence = 'working' | 'later' | 'finished' | 'off'

export interface PersonToday {
  person: TeamPerson
  /** The shift that explains the status (running, next or last one); null when off. */
  shift: TeamShift | null
}

export interface TodaySummary {
  date: string
  /** `HH:mm`, company-local */
  time: string
  holiday: TeamDay['holiday']
  groups: Record<Presence, PersonToday[]>
}

const crossesMidnight = (shift: TeamShift) => shift.ends_at <= shift.starts_at

function previousDate(date: string): string {
  const day = parseLocalDate(date)
  day.setDate(day.getDate() - 1)
  return toLocalDateString(day)
}

function isRunning(shift: TeamShift, time: string): boolean {
  return crossesMidnight(shift) ? time >= shift.starts_at : shift.starts_at <= time && time < shift.ends_at
}

function classify(todayShifts: TeamShift[], fromYesterday: TeamShift[], time: string): { presence: Presence; shift: TeamShift | null } {
  const running = fromYesterday.find((shift) => time < shift.ends_at) ?? todayShifts.find((shift) => isRunning(shift, time))
  if (running) return { presence: 'working', shift: running }
  const next = todayShifts.find((shift) => time < shift.starts_at)
  if (next) return { presence: 'later', shift: next }
  const last = todayShifts.at(-1)
  return last ? { presence: 'finished', shift: last } : { presence: 'off', shift: null }
}

/**
 * Needs the calendar to include today (and yesterday, for night shifts still running). Returns null when
 * today is not in the range.
 */
export function summarizeToday(calendar: TeamCalendar, now: Date): TodaySummary | null {
  const { date, time } = zonedPartsOf(now, calendar.timezone)
  const today = calendar.days.find((day) => day.date === date)
  if (!today) return null
  const yesterday = calendar.days.find((day) => day.date === previousDate(date))

  const groups: Record<Presence, PersonToday[]> = { working: [], later: [], finished: [], off: [] }
  for (const person of calendar.people) {
    const todayShifts = today.shifts.filter((shift) => shift.user_id === person.id)
    const fromYesterday = (yesterday?.shifts ?? []).filter((shift) => shift.user_id === person.id && crossesMidnight(shift))
    const { presence, shift } = classify(todayShifts, fromYesterday, time)
    groups[presence].push({ person, shift })
  }

  return { date, time, holiday: today.holiday, groups }
}
