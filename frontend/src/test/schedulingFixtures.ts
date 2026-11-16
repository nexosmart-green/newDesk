import type { TeamCalendar, TeamPerson, TeamShift } from '@/features/calendar/teamCalendarTypes'
import type { ShiftRecurrence } from '@/features/shifts/recurrenceTypes'
import { datesBetween } from '@/lib/week'

export function buildRecurrence(overrides: Partial<ShiftRecurrence> = {}): ShiftRecurrence {
  return {
    id: 1,
    user: { id: 1, full_name: 'Ana García' },
    template: { id: 3, name: 'Completo', color: '#309798', starts_at: '09:00', ends_at: '18:00', mode: 'office' },
    weekdays: [1, 2, 3, 4, 5],
    starts_on: '2026-11-02',
    ends_on: null,
    skip_holidays: true,
    created_at: '2026-11-01T12:00:00+00:00',
    ...overrides,
  }
}

export function buildTeamPerson(overrides: Partial<TeamPerson> = {}): TeamPerson {
  return { id: 1, full_name: 'Ana García', avatar: null, job_title: null, ...overrides }
}

export function buildTeamShift(overrides: Partial<TeamShift> = {}): TeamShift {
  return { id: 1, user_id: 1, starts_at: '09:00', ends_at: '18:00', mode: 'office', template_name: 'Completo', color: '#309798', ...overrides }
}

/** A team calendar for `from..to` where `shiftsByDate` fills some days. */
export function buildTeamCalendar(
  from: string,
  to: string,
  people: TeamPerson[],
  shiftsByDate: Record<string, TeamShift[]> = {},
  holidays: Record<string, string> = {},
): TeamCalendar {
  return {
    timezone: 'America/Argentina/Buenos_Aires',
    people,
    days: datesBetween(from, to).map((date) => {
      const holiday = holidays[date]
      return { date, holiday: holiday ? { name: holiday, scope: 'national' as const } : null, shifts: shiftsByDate[date] ?? [] }
    }),
  }
}
