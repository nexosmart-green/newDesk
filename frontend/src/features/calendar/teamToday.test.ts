import { summarizeToday } from './teamToday'
import type { TeamCalendar, TeamShift } from './teamCalendarTypes'

const person = (id: number, full_name: string) => ({ id, full_name, avatar: null, job_title: null })
const shift = (id: number, user_id: number, starts_at: string, ends_at: string): TeamShift => ({
  id,
  user_id,
  starts_at,
  ends_at,
  mode: 'office',
  template_name: 'Turno',
  color: '#309798',
})

const calendar: TeamCalendar = {
  timezone: 'America/Argentina/Buenos_Aires',
  people: [person(1, 'Ana'), person(2, 'Beto'), person(3, 'Caro'), person(4, 'Dani'), person(5, 'Eli')],
  days: [
    { date: '2026-11-11', holiday: null, shifts: [shift(1, 5, '22:00', '06:00')] },
    {
      date: '2026-11-12',
      holiday: null,
      shifts: [shift(2, 1, '09:00', '18:00'), shift(3, 2, '14:00', '20:00'), shift(4, 3, '06:00', '09:00')],
    },
  ],
}

const names = (items: { person: { full_name: string } }[]) => items.map((item) => item.person.full_name)

describe('summarizeToday', () => {
  it('splits people into working now, later, finished and off by the company wall clock', () => {
    // 12:30 UTC = 09:30 in Buenos Aires, whatever the test machine's zone.
    const summary = summarizeToday(calendar, new Date('2026-11-12T12:30:00Z'))

    expect(summary?.date).toBe('2026-11-12')
    expect(names(summary?.groups.working ?? [])).toEqual(['Ana'])
    expect(names(summary?.groups.later ?? [])).toEqual(['Beto'])
    expect(names(summary?.groups.finished ?? [])).toEqual(['Caro'])
    expect(names(summary?.groups.off ?? [])).toEqual(['Dani', 'Eli'])
  })

  it('keeps a night shift from yesterday as working until it ends', () => {
    const summary = summarizeToday(calendar, new Date('2026-11-12T08:00:00Z')) // 05:00 local

    expect(names(summary?.groups.working ?? [])).toEqual(['Eli'])
    expect(summary?.groups.working[0]?.shift?.starts_at).toBe('22:00')
  })

  it('returns null when today is outside the range', () => {
    expect(summarizeToday(calendar, new Date('2026-11-20T12:00:00Z'))).toBeNull()
  })
})
