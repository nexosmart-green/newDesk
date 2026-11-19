import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCan, useCurrentUser, useProfileTimeZone } from '@/features/auth/useAuth'
import { monthOf } from '@/features/calendar/calendarDays'
import { ACTIVE_PROJECTS, useProjects } from '@/features/projects/useProjects'
import { fieldErrorOf } from '@/lib/api'
import { todayIn } from '@/lib/timezone'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { HoursCalendar } from './HoursCalendar'
import { MyHoursFilters } from './MyHoursFilters'
import { MyHoursList } from './MyHoursList'
import { thisMonthEntryFilters, type MyEntryFilters } from './types'
import { useEntries } from './useHours'

/** Closed projects too: old hours were logged in them. */
const ALL_PROJECTS = { ...ACTIVE_PROJECTS, status: '' } as const

type View = 'lista' | 'calendario'

const rangeErrorOf = ({ from, to }: MyEntryFilters) =>
  from && to && from > to ? 'La fecha «Desde» no puede ser posterior a «Hasta».' : undefined

/**
 * RF-05.20 "Mis horas": every entry of mine across projects for a range (this month by default), with the total,
 * the CSV and the fixes allowed by the window (RF-05.15); or by month in the calendar. `time.manage` picks a person.
 */
export function MyHoursPage() {
  const { data: user } = useCurrentUser()
  const canPickPerson = useCan('time.manage')
  const timeZone = useProfileTimeZone()
  const projects = useProjects(ALL_PROJECTS)
  const [filters, setFilters] = useState(() => thisMonthEntryFilters(todayIn(timeZone)))
  const [page, setPage] = useState(1)
  const [view, setView] = useState<View>('lista')
  const [month, setMonth] = useState(() => monthOf(new Date()))
  const rangeError = rangeErrorOf(filters)
  const applied = { ...filters, search: useDebouncedValue(filters.search) }
  const ready = Boolean(filters.from && filters.to) && !rangeError
  const entries = useEntries(applied, page, ready && view === 'lista')

  function change(changes: Partial<MyEntryFilters>) {
    setPage(1)
    setFilters((current) => ({ ...current, ...changes }))
  }

  return (
    <section className="max-w-3xl space-y-4">
      <PageHeader
        title="Mis horas"
        actions={
          <div role="group" aria-label="Vista" className="flex gap-1">
            {(['lista', 'calendario'] as const).map((option) => (
              <Button key={option} variant={view === option ? 'primary' : 'secondary'} aria-pressed={view === option} onClick={() => setView(option)}>
                {option === 'lista' ? 'Lista' : 'Calendario'}
              </Button>
            ))}
          </div>
        }
      />
      <MyHoursFilters
        filters={filters}
        projects={(projects.data?.data ?? []).map((project) => ({ id: project.id, name: project.name }))}
        canPickPerson={canPickPerson}
        viewerId={user?.id}
        showRange={view === 'lista'}
        rangeError={rangeError}
        fromError={fieldErrorOf(entries.error, 'from')}
        toError={fieldErrorOf(entries.error, 'to')}
        onChange={change}
      />
      {view === 'calendario' ? (
        <HoursCalendar
          month={month}
          filters={filters}
          onMonthChange={setMonth}
          onPickDay={(date) => {
            change({ from: date, to: date })
            setView('lista')
          }}
        />
      ) : (
        ready && <MyHoursList entries={entries} filters={applied} onPageChange={setPage} />
      )}
    </section>
  )
}
