import { formatDate } from '@/lib/dates'

function dayDiff(from: string, to: string): number {
  return Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 86_400_000)
}

/** "hoy", "mañana", "en 3 días" up to a week; the date beyond that. */
export function dueText(dueOn: string, today: string): string {
  const days = dayDiff(today, dueOn)
  if (days === 0) return 'Vence hoy'
  if (days === 1) return 'Vence mañana'
  if (days > 1 && days <= 7) return `Vence en ${days} días`
  return `Vence ${formatDate(dueOn)}`
}
