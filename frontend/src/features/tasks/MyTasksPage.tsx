import { PageState } from '@/components/ui/PageState'
import { useCurrentUser } from '@/features/auth/useAuth'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { TaskFiltersBar } from './TaskFiltersBar'
import { TaskList } from './TaskList'
import { useMyTasks, useTaskStatuses } from './useTasks'
import { useTaskFilters } from './useTaskFilters'

/** RF-04.07 "Mis tareas": what each person opens in the morning, across projects (mobile first). */
export function MyTasksPage() {
  const { data: user } = useCurrentUser()
  const { filters, change } = useTaskFilters('mine')
  const statuses = useTaskStatuses()
  const tasks = useMyTasks({ ...filters, search: useDebouncedValue(filters.search) })

  return (
    <section className="max-w-3xl space-y-4">
      <header>
        <h1 className="text-lg font-semibold">Hola, {user?.first_name}</h1>
        <h2 className="text-sm text-muted">Mis tareas</h2>
      </header>
      <TaskFiltersBar filters={filters} statuses={statuses.data ?? []} onChange={change} />
      {!tasks.data ? (
        <PageState isPending={tasks.isPending} error={tasks.error} shape="list" onRetry={() => void tasks.refetch()} />
      ) : (
        <TaskList tasks={tasks.data.data} userId={user?.id} showProject />
      )}
    </section>
  )
}
