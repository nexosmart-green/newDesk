import { QuickLinks } from '@/components/layout/QuickLinks'
import { PageHeader } from '@/components/ui/PageHeader'
import { PageState } from '@/components/ui/PageState'
import { Skeleton } from '@/components/ui/Skeleton'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCurrentUser, useProfileTimeZone } from '@/features/auth/useAuth'
import { BagsRunningOut } from './BagsRunningOut'
import { DueSoonList } from './DueSoonList'
import { NowBand } from './NowBand'
import { TaskCounters } from './TaskCounters'
import { useDashboard } from './useDashboard'
import { WeekHours } from './WeekHours'

function longToday(timeZone: string): string {
  return new Date().toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long', timeZone })
}

/**
 * `/` Inicio, read top to bottom like a work log: what runs now, what is pending (one sentence), how the
 * week goes, what is due, which bags need a word with the client. Blocks the role can't see are absent.
 */
export function DashboardPage() {
  const { data: user } = useCurrentUser()
  const timeZone = useProfileTimeZone()
  const dashboard = useDashboard()
  const hasTime = useHasModule(MODULES.time)
  const data = dashboard.data

  return (
    <section className="max-w-6xl space-y-5">
      <PageHeader
        title={`Hola, ${user?.first_name ?? ''}`}
        description={<span className="inline-block first-letter:uppercase">{longToday(timeZone)}</span>}
      />
      {hasTime && <NowBand />}
      {!data ? (
        <PageState isPending={dashboard.isPending} error={dashboard.error} skeleton={<DashboardSkeleton />} onRetry={() => void dashboard.refetch()} />
      ) : !data.tasks && !data.projects && !data.week && !data.bags_running_out ? (
        <>
          <p className="text-muted">Elegí por dónde seguir.</p>
          <QuickLinks />
        </>
      ) : (
        <>
          <TaskCounters tasks={data.tasks} projects={data.projects} />
          {(data.week || data.tasks) && (
            <div className={`grid gap-4 ${data.week && data.tasks ? 'lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]' : ''}`}>
              {data.week && <WeekHours week={data.week} />}
              {data.tasks && <DueSoonList tasks={data.tasks.due_soon} />}
            </div>
          )}
          {data.bags_running_out && <BagsRunningOut bags={data.bags_running_out} />}
        </>
      )}
    </section>
  )
}

function DashboardSkeleton() {
  return (
    <div role="status" aria-live="polite" className="space-y-4">
      <span className="sr-only">Cargando el panel…</span>
      <Skeleton className="h-6 w-2/3 max-w-xl" />
      <div className="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <Skeleton className="h-56 rounded-lg" />
        <Skeleton className="h-56 rounded-lg" />
      </div>
    </div>
  )
}
