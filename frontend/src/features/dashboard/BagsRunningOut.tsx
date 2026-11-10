import { Link } from 'react-router'
import { HoursBar } from '@/features/hours/HoursBar'
import type { HourBag } from '@/features/hours/types'
import { formatDuration } from '@/lib/duration'
import type { BagRunningOut } from './types'

function bagOf(bag: BagRunningOut): HourBag {
  const available = Math.max(0, bag.allocated_seconds - bag.consumed_seconds)
  return {
    has_cap: true,
    allocated_seconds: bag.allocated_seconds,
    consumed_seconds: bag.consumed_seconds,
    running_seconds: 0,
    available_seconds: available,
    percent_consumed: bag.allocated_seconds > 0 ? Math.round((bag.consumed_seconds / bag.allocated_seconds) * 100) : 100,
    is_exhausted: available === 0,
  }
}

/** Active hour bags at 80 % or more (who sells or manages hours sees it): time to talk to the client. */
export function BagsRunningOut({ bags }: { bags: BagRunningOut[] }) {
  return (
    <section aria-labelledby="bags-title" className="rounded-xl border border-border bg-surface p-4 sm:p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="bags-title" className="font-semibold">
          Bolsas por agotarse
        </h2>
        <Link to="/proyectos" className="text-sm text-accent hover:underline">
          Ver proyectos
        </Link>
      </div>
      {bags.length === 0 ? (
        <p className="mt-2 text-sm text-muted">Ninguna bolsa activa pasó el 80 % de consumo.</p>
      ) : (
        <ul className="mt-3 grid gap-x-6 gap-y-4 md:grid-cols-2">
          {bags.map((bag) => {
            const left = Math.max(0, bag.allocated_seconds - bag.consumed_seconds)
            return (
              <li key={bag.id} className="space-y-1.5">
                <div className="flex items-baseline justify-between gap-2">
                  <Link to={`/proyectos/${bag.id}/horas`} className="truncate text-sm font-medium hover:underline">
                    {bag.name}
                  </Link>
                  <span className="tabular shrink-0 text-xs text-muted">
                    {left === 0 ? 'Sin horas' : `Quedan ${formatDuration(left)}`}
                  </span>
                </div>
                <HoursBar bag={bagOf(bag)} compact />
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}
