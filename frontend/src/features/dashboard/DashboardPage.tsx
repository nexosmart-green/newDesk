import { QuickLinks } from '@/components/layout/QuickLinks'
import { PageState } from '@/components/ui/PageState'
import { Skeleton } from '@/components/ui/Skeleton'
import { useCurrentUser, useProfileTimeZone } from '@/features/auth/useAuth'
import { BagsRunningOut } from './BagsRunningOut'
import { DueSoonList } from './DueSoonList'
import { TaskCounters } from './TaskCounters'
import { useDashboard } from './useDashboard'
import { WeekHours } from './WeekHours'

function longToday(timeZone: string): string {
  return new Date().toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long', timeZone })
}

/** `/` "Panel": what's pending, how the week goes and which bags need a word with the client. Blocks the role can't see are absent. */
export function DashboardPage() {
  const { data: user } = useCurrentUser()
  const timeZone = useProfileTimeZone()
  const dashboard = useDashboard()
  const data = dashboard.data

  return (
    <section className="max-w-6xl space-y-5">
      <header className="space-y-0.5">
        <h1 className="text-2xl font-semibold tracking-tight">Hola, {user?.first_name}</h1>
        <p className="text-sm text-muted first-letter:uppercase">{longToday(timeZone)}</p>
      </header>
      {!data ? (
        dashboard.isPending ? (
          <DashboardSkeleton />
        ) : (
          <PageState isPending={false} error={dashboard.error} onRetry={() => void dashboard.refetch()} />
        )
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
      <Skeleton className="h-20 w-full rounded-xl" />
      <div className="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <Skeleton className="h-72 rounded-xl" />
        <Skeleton className="h-72 rounded-xl" />
      </div>
    </div>
  )
}
