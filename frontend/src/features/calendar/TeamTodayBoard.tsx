import { CalendarOff } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import { Skeleton } from '@/components/ui/Skeleton'
import { useOtherTimeZone } from '@/features/shifts/useOtherTimeZone'
import { cityOf } from '@/features/shifts/shiftTime'
import { dayLabel } from '@/lib/week'
import { holidayScopeLabels } from './types'
import type { TeamCalendar } from './teamCalendarTypes'
import { summarizeToday, type PersonToday, type Presence } from './teamToday'
import { useNow } from './useNow'
import { WorkModeIcon } from './WorkModeIcon'

const lanes: readonly { presence: Presence; title: string; empty: string; detail: (item: PersonToday) => string }[] = [
  { presence: 'working', title: 'Trabajando ahora', empty: 'Nadie en turno en este momento.', detail: (item) => `hasta las ${item.shift?.ends_at}` },
  { presence: 'later', title: 'Más tarde', empty: 'No entra nadie más hoy.', detail: (item) => `entra a las ${item.shift?.starts_at}` },
  { presence: 'finished', title: 'Ya terminó', empty: 'Nadie terminó todavía.', detail: (item) => `salió a las ${item.shift?.ends_at}` },
  { presence: 'off', title: 'Sin turno', empty: 'Todos tienen turno hoy.', detail: (item) => item.person.job_title ?? '' },
]

function PersonLine({ item, detail }: { item: PersonToday; detail: string }) {
  return (
    <li className="flex min-h-11 items-center gap-3">
      <Avatar name={item.person.full_name} url={item.person.avatar?.sm} size="sm" />
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-medium">{item.person.full_name}</span>
        {detail && <span className="tabular block truncate text-xs text-muted">{detail}</span>}
      </span>
      {item.shift && <WorkModeIcon mode={item.shift.mode} />}
    </li>
  )
}

/** RF-06.05 "Quién está hoy": live lanes by the company clock. Leaves join here in R2. */
export function TeamTodayBoard({ calendar }: { calendar: TeamCalendar }) {
  const now = useNow()
  const otherZone = useOtherTimeZone(calendar.timezone)
  const summary = summarizeToday(calendar, now)
  if (!summary) return null

  return (
    <section aria-labelledby="team-today-title" className="space-y-3">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="team-today-title" className="text-base font-semibold first-letter:uppercase">
          Hoy, {dayLabel(summary.date)}
        </h2>
        <p className="tabular text-xs text-muted">
          Son las {summary.time}
          {otherZone && ` (hora de ${cityOf(calendar.timezone)})`}
        </p>
      </div>
      {summary.holiday && (
        <p className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
          <CalendarOff aria-hidden="true" className="size-4 shrink-0 text-muted" />
          <span className="font-medium">{summary.holiday.name}</span>
          <span className="text-muted">Feriado {holidayScopeLabels[summary.holiday.scope].toLowerCase()}</span>
        </p>
      )}
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {lanes.map((lane) => {
          const items = summary.groups[lane.presence]
          return (
            <section key={lane.presence} aria-label={`${lane.title}: ${items.length}`} className="rounded-lg border border-line bg-surface">
              <h3 className="flex items-center gap-2 px-4 pt-3 text-sm font-semibold">
                {lane.presence === 'working' && <span aria-hidden="true" className="size-2 rounded-full bg-accent" />}
                {lane.title}
                <span className="tabular ml-auto text-xs font-normal text-muted">{items.length}</span>
              </h3>
              {items.length === 0 ? (
                <p className="px-4 pb-4 pt-2 text-sm text-muted">{lane.empty}</p>
              ) : (
                <ul className="space-y-1 px-4 pb-3 pt-2">
                  {items.map((item) => (
                    <PersonLine key={item.person.id} item={item} detail={lane.detail(item)} />
                  ))}
                </ul>
              )}
            </section>
          )
        })}
      </div>
    </section>
  )
}

export function TeamTodaySkeleton() {
  return (
    <div role="status" aria-live="polite" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <span className="sr-only">Cargando quién está hoy…</span>
      {Array.from({ length: 4 }, (_, index) => (
        <div key={index} className="space-y-3 rounded-lg border border-line bg-surface p-4">
          <Skeleton className="h-4 w-1/2" />
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-4/5" />
        </div>
      ))}
    </div>
  )
}
