/** Week math on LOCAL calendar dates (`YYYY-MM-DD`); never via toISOString, which shifts to UTC. */

const pad = (value: number) => value.toString().padStart(2, '0')

export function toLocalDateString(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** `YYYY-MM-DD` → local midnight Date. */
export function parseLocalDate(value: string): Date {
  const [year = 0, month = 1, day = 1] = value.split('-').map(Number)
  return new Date(year, month - 1, day)
}

export function isLocalDateString(value: string | null): value is string {
  return value !== null && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(parseLocalDate(value).getTime())
}

export interface Week {
  /** Monday */
  from: string
  /** Sunday */
  to: string
}

/** RF-05.12: Monday–Sunday week containing `date`. */
export function weekOf(date: Date): Week {
  const monday = new Date(date.getFullYear(), date.getMonth(), date.getDate())
  const daysSinceMonday = (monday.getDay() + 6) % 7
  monday.setDate(monday.getDate() - daysSinceMonday)
  const sunday = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6)
  return { from: toLocalDateString(monday), to: toLocalDateString(sunday) }
}

export function shiftWeek(monday: string, weeks: number): Week {
  const date = parseLocalDate(monday)
  date.setDate(date.getDate() + weeks * 7)
  return weekOf(date)
}

const dayMonth = (date: Date) =>
  date.toLocaleDateString('es-AR', { day: 'numeric', month: 'short' }).replace('.', '')

/** "3 nov – 9 nov 2026" */
export function weekLabel(week: Week): string {
  const from = parseLocalDate(week.from)
  const to = parseLocalDate(week.to)
  return `${dayMonth(from)} – ${dayMonth(to)} ${to.getFullYear()}`
}

/** "lunes 3/11" */
export function dayLabel(value: string): string {
  const date = parseLocalDate(value)
  return `${date.toLocaleDateString('es-AR', { weekday: 'long' })} ${date.getDate()}/${date.getMonth() + 1}`
}

/** Every calendar date from `from` to `to`, both included. */
export function datesBetween(from: string, to: string): string[] {
  const dates: string[] = []
  const end = parseLocalDate(to)
  for (const day = parseLocalDate(from); day <= end; day.setDate(day.getDate() + 1)) dates.push(toLocalDateString(day))
  return dates
}

/** ISO weekday: 1 = Monday … 7 = Sunday. */
export function isoWeekdayOf(value: string): number {
  return ((parseLocalDate(value).getDay() + 6) % 7) + 1
}
