import { cityOf } from './shiftTime'
import { useOtherTimeZone } from './useOtherTimeZone'

/** "Hora de Buenos Aires" — only for someone whose own time zone differs; everyone else needs no note. */
export function CompanyTimeNote({ timeZone, className = '' }: { timeZone: string; className?: string }) {
  const differs = useOtherTimeZone(timeZone)
  if (!differs) return null
  return <p className={`text-xs text-muted ${className}`}>Hora de {cityOf(timeZone)}</p>
}
