/** "09:00" → "9", "09:30" → "9:30": a shift's hours read like people say them. */
export function compactTime(time: string): string {
  const [hours = '0', minutes = '00'] = time.split(':')
  const hour = String(Number(hours))
  return minutes === '00' ? hour : `${hour}:${minutes}`
}

/** "9–18", "9:30–13". */
export function timeRange(startsAt: string, endsAt: string): string {
  return `${compactTime(startsAt)}–${compactTime(endsAt)}`
}

/** "America/Argentina/Buenos_Aires" → "Buenos Aires". */
export function cityOf(timeZone: string): string {
  return (timeZone.split('/').pop() ?? timeZone).replace(/_/g, ' ')
}

/** Weekend and holiday columns: a quieter tone, so working days stand out. */
export const restDayTint = 'bg-surface-2/60'
