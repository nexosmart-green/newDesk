import { zonedPartsOf } from '@/lib/timezone'

/**
 * Entry instants are UTC; forms and rows show them in the person's PROFILE timezone (`/me.timezone`),
 * the same one the backend uses to read the date + from/to the person types.
 */
export function entryFormTimes(startedAt: string, endedAt: string, timeZone: string) {
  const start = zonedPartsOf(startedAt, timeZone)
  return { date: start.date, from: start.time, to: zonedPartsOf(endedAt, timeZone).time }
}

/** "06/11/2026 10:00–11:30" for an entry row. */
export function formatEntryRange(startedAt: string, endedAt: string | null, timeZone: string): string {
  const start = zonedPartsOf(startedAt, timeZone)
  const [year, month, day] = start.date.split('-')
  return `${day}/${month}/${year} ${start.time}–${endedAt ? zonedPartsOf(endedAt, timeZone).time : 'en curso'}`
}
