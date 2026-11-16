import { Briefcase, Network } from 'lucide-react'

interface PersonRoleProps {
  person: { job_title?: { name: string } | null; area?: { name: string } | null }
  /** Said instead when the person has neither a position nor an area. */
  fallback?: string
}

/** Position and area as two quiet facts side by side, each with its icon (not "Puesto · Área"). */
export function PersonRole({ person, fallback }: PersonRoleProps) {
  const job = person.job_title?.name
  const area = person.area?.name
  if (!job && !area) return fallback ? <p className="truncate text-sm text-muted">{fallback}</p> : null
  return (
    <p className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-muted">
      {job && (
        <span className="inline-flex min-w-0 items-center gap-1.5">
          <Briefcase aria-hidden="true" className="size-3.5 shrink-0" strokeWidth={1.75} />
          <span className="sr-only">Puesto:</span>
          <span className="truncate">{job}</span>
        </span>
      )}
      {area && (
        <span className="inline-flex min-w-0 items-center gap-1.5">
          <Network aria-hidden="true" className="size-3.5 shrink-0" strokeWidth={1.75} />
          <span className="sr-only">Área:</span>
          <span className="truncate">{area}</span>
        </span>
      )}
    </p>
  )
}
