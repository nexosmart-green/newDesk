import { Link } from 'react-router'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { formatDate } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import { todayIn } from '@/lib/timezone'
import type { DashboardWeek } from './types'

/** A full working day: the dashed reference line, and the scale's floor so a short day looks short. */
const WORKDAY_SECONDS = 8 * 3600

function weekdayOf(date: string): string {
  // Noon avoids any timezone shift turning the calendar date into the previous day.
  return new Date(`${date}T12:00:00`).toLocaleDateString('es-AR', { weekday: 'short' }).replace('.', '')
}

function shortHours(seconds: number): string {
  if (seconds === 0) return '—'
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  return hours === 0 ? `${minutes}m` : minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`
}

/** Mi semana in one look: a bar per day against an 8 h line, today marked. Lunes a domingo, mis horas. */
export function WeekHours({ week }: { week: DashboardWeek }) {
  const today = todayIn(useProfileTimeZone())
  const scale = Math.max(WORKDAY_SECONDS, ...week.days.map((day) => day.seconds))
  const workdayAt = (WORKDAY_SECONDS / scale) * 100

  return (
    <section aria-labelledby="week-hours-title" className="rounded-xl border border-border bg-surface p-4 sm:p-5">
      <header className="flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <h2 id="week-hours-title" className="font-semibold">
            Mi semana
          </h2>
          <p className="text-xs text-muted">
            {formatDate(week.from)} al {formatDate(week.to)}
          </p>
        </div>
        <p className="tabular text-2xl font-semibold tracking-tight">
          {formatDuration(week.total_seconds)}
          <span className="sr-only"> registradas en la semana</span>
        </p>
      </header>

      <div className="relative mt-5 h-40" aria-hidden="true">
        <div className="absolute inset-x-0 border-t border-dashed border-muted/40" style={{ bottom: `${workdayAt}%` }}>
          <span className="absolute -top-2.5 right-0 bg-surface pl-1 text-[0.6875rem] text-muted">8 h</span>
        </div>
        <div className="absolute inset-0 grid grid-cols-7 items-end gap-2 sm:gap-3">
          {week.days.map((day) => {
            const isToday = day.date === today
            const height = day.seconds === 0 ? 0 : Math.max(3, (day.seconds / scale) * 100)
            return (
              <div key={day.date} className="flex h-full flex-col justify-end">
                <div
                  className={`w-full rounded-t-md transition-[height] duration-300 ${isToday ? 'bg-brand' : 'bg-brand/35'}`}
                  style={{ height: `${height}%` }}
                />
              </div>
            )
          })}
        </div>
      </div>
      <ol className="mt-2 grid grid-cols-7 gap-2 border-t border-border pt-2 text-center sm:gap-3">
        {week.days.map((day) => {
          const isToday = day.date === today
          return (
            <li key={day.date} aria-current={isToday ? 'date' : undefined} className="min-w-0">
              <span className={`block text-xs capitalize ${isToday ? 'font-semibold text-accent' : 'text-muted'}`}>
                {weekdayOf(day.date)}
                <span className="sr-only"> {formatDate(day.date)}{isToday ? ' (hoy)' : ''}:</span>
              </span>
              <span className={`tabular block truncate text-xs ${isToday ? 'font-semibold' : ''}`}>{shortHours(day.seconds)}</span>
            </li>
          )
        })}
      </ol>
      <Link to="/mi-semana" className="mt-3 inline-block text-sm text-accent hover:underline">
        Ver el detalle de la semana
      </Link>
    </section>
  )
}
