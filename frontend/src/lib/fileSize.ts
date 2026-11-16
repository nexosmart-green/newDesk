const units = ['KB', 'MB', 'GB'] as const

/** Bytes as people read them: "820 B", "14 KB", "3,4 MB" (decimal comma, 1024 steps like the OS). */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  let value = bytes / 1024
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  const digits = value < 10 && unit > 0 ? 1 : 0
  return `${value.toLocaleString('es-AR', { maximumFractionDigits: digits })} ${units[unit]}`
}

/** "informe.final.PDF" → "pdf"; "" when there is none. */
export function fileExtension(name: string): string {
  const match = /\.([a-z0-9]+)$/i.exec(name)
  return match?.[1]?.toLowerCase() ?? ''
}
