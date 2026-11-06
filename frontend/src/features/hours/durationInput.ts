/** R-5: hours + minutes typed by a person → integer seconds. Empty or invalid parts count as 0. */
export function toSeconds(hours: string, minutes: string): number {
  const wholeHours = Math.trunc(Number(hours) || 0)
  const wholeMinutes = Math.trunc(Number(minutes) || 0)
  return Math.max(0, wholeHours) * 3600 + Math.max(0, wholeMinutes) * 60
}

export interface DurationValue {
  hours: string
  minutes: string
}

export const emptyDuration: DurationValue = { hours: '', minutes: '' }
