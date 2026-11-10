import { zonedPartsOf } from './timezone'

/** `YYYY-MM-DD` (a calendar date, no time zone) → `dd/mm/aaaa`, without shifting the day. */
export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const [year, month, day] = value.split('-')
  return year && month && day ? `${day}/${month}/${year}` : value
}

export function formatDateTime(isoValue: string): string {
  return new Date(isoValue).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' })
}

/** An instant as `dd/mm/aaaa hh:mm` in a given IANA timezone (the person's profile one). */
export function formatInstant(isoValue: string, timeZone: string): string {
  const { date, time } = zonedPartsOf(isoValue, timeZone)
  return `${formatDate(date)} ${time}`
}
