/**
 * Wall-clock parts of an instant in an IANA timezone, via Intl (no date library). The backend reads manual
 * dates/times in the person's PROFILE timezone, which may differ from the browser's.
 */
interface ZonedParts {
  /** `YYYY-MM-DD` */
  date: string
  /** `HH:mm` (24 h) */
  time: string
}

const formatters = new Map<string, Intl.DateTimeFormat>()

function formatterFor(timeZone: string): Intl.DateTimeFormat {
  let formatter = formatters.get(timeZone)
  if (!formatter) {
    formatter = new Intl.DateTimeFormat('en-CA', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
    formatters.set(timeZone, formatter)
  }
  return formatter
}

export function browserTimeZone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone
}

export function zonedPartsOf(instant: string | Date, timeZone: string): ZonedParts {
  const parts: Record<string, string> = {}
  for (const part of formatterFor(timeZone).formatToParts(new Date(instant))) parts[part.type] = part.value
  return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` }
}

/** Today's calendar date (`YYYY-MM-DD`) in `timeZone`. */
export function todayIn(timeZone: string, now: Date = new Date()): string {
  return zonedPartsOf(now, timeZone).date
}
