/** R-5: all time math is integer seconds; the UI renders `h m`. */
export function formatDuration(totalSeconds: number): string {
  const safe = Math.max(0, Math.trunc(totalSeconds))
  const hours = Math.floor(safe / 3600)
  const minutes = Math.floor((safe % 3600) / 60)
  return `${hours}h ${minutes.toString().padStart(2, '0')}m`
}

/** Clock format for the running timer in the header: `HH:MM:SS`. */
export function formatClock(totalSeconds: number): string {
  const safe = Math.max(0, Math.trunc(totalSeconds))
  const pad = (value: number) => value.toString().padStart(2, '0')
  return `${pad(Math.floor(safe / 3600))}:${pad(Math.floor((safe % 3600) / 60))}:${pad(safe % 60)}`
}
