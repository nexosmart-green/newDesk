import { useSearchParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { StaleContent } from '@/components/ui/StaleContent'
import { useCurrentUser } from '@/features/auth/useAuth'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { TaskFiltersBar } from './TaskFiltersBar'
import { TaskList } from './TaskList'
import { TasksEmptyState } from './TasksEmptyState'
import { defaultTaskFilters } from './types'
import { useMyTasks, useTaskStatuses } from './useTasks'
import { useTaskFilters } from './useTaskFilters'

/** RF-04.07 "Mis tareas": what each person works on, across projects (mobile first). `?vencidas=1` opens on the overdue ones. */
export function MyTasksPage() {
  const { data: user } = useCurrentUser()
  const [searchParams] = useSearchParams()
  const { filters, change } = useTaskFilters('mine', searchParams.has('vencidas') ? { overdue: true } : undefined)
  const statuses = useTaskStatuses()
  const tasks = useMyTasks({ ...filters, search: useDebouncedValue(filters.search) })

  return (
    <section className="max-w-4xl space-y-4">
      <header className="space-y-0.5">
        <h1 className="text-lg font-semibold">Mis tareas</h1>
        <p className="text-sm text-muted">Las tareas que tenés asignadas, de todos tus proyectos.</p>
      </header>
      <TaskFiltersBar filters={filters} statuses={statuses.data ?? []} onChange={change} />
      {!tasks.data ? (
        <PageState isPending={tasks.isPending} error={tasks.error} shape="list" onRetry={() => void tasks.refetch()} />
      ) : (
        <StaleContent stale={tasks.isPlaceholderData}>
          <TaskList
            tasks={tasks.data.data}
            userId={user?.id}
            showProject
            empty={<TasksEmptyState filters={filters} onReset={() => change({ ...defaultTaskFilters })} />}
          />
        </StaleContent>
      )}
    </section>
  )
}
