const pad = (value: number) => value.toString().padStart(2, '0')

/** ISO-8601 instant → `YYYY-MM-DD` in the browser's timezone. */
export function localDateOf(iso: string): string {
  const date = new Date(iso)
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** ISO-8601 instant → `HH:mm` in the browser's timezone. */
export function localTimeOf(iso: string): string {
  const date = new Date(iso)
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** "06/11/2026 10:00–11:30" for an entry row. */
export function formatEntryRange(startedAt: string, endedAt: string | null): string {
  const [year, month, day] = localDateOf(startedAt).split('-')
  return `${day}/${month}/${year} ${localTimeOf(startedAt)}–${endedAt ? localTimeOf(endedAt) : 'en curso'}`
}
