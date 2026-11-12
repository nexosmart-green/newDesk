import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { StaleContent } from '@/components/ui/StaleContent'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCurrentUser } from '@/features/auth/useAuth'
import { emptyDirectoryFilters } from '@/features/people/types'
import { usePeople } from '@/features/people/usePeople'
import { useProjects } from '@/features/projects/useProjects'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { TaskFiltersBar } from './TaskFiltersBar'
import { TaskList } from './TaskList'
import { TasksEmptyState } from './TasksEmptyState'
import { defaultTaskFilters, type TaskFilters } from './types'
import { useAllTasks, useTaskStatuses } from './useTasks'
import { useTaskFilters } from './useTaskFilters'

const ALL_PROJECTS = { client_id: null, status: '', search: '', running_out: false, page: 1 } as const

/** "Todas las tareas": every task of the projects the viewer can see, across projects, paged. */
export function AllTasksPage() {
  const userId = useCurrentUser().data?.id
  const { filters, change } = useTaskFilters('all')
  const [page, setPage] = useState(1)
  const statuses = useTaskStatuses()
  const tasks = useAllTasks({ ...filters, search: useDebouncedValue(filters.search) }, page)
  const projects = useProjects(ALL_PROJECTS)
  const hasPeople = useHasModule(MODULES.people)
  const people = usePeople(emptyDirectoryFilters, hasPeople)

  function changeFilters(changes: Partial<TaskFilters>) {
    setPage(1)
    change(changes)
  }

  return (
    <section className="max-w-5xl space-y-4">
      <header className="flex flex-wrap items-end justify-between gap-2">
        <div className="space-y-0.5">
          <h1 className="text-lg font-semibold">Tareas</h1>
          <p className="text-sm text-muted">Todas las tareas de los proyectos que podés ver.</p>
        </div>
        {tasks.data && (
          <p className="text-sm text-muted" aria-live="polite">
            {tasks.data.meta.total === 1 ? '1 tarea' : `${tasks.data.meta.total} tareas`}
          </p>
        )}
      </header>
      <TaskFiltersBar
        filters={filters}
        statuses={statuses.data ?? []}
        projects={(projects.data?.data ?? []).map((project) => ({ id: project.id, name: project.name }))}
        members={hasPeople ? (people.data?.data ?? []) : undefined}
        onChange={changeFilters}
      />
      {!tasks.data ? (
        <PageState isPending={tasks.isPending} error={tasks.error} shape="list" onRetry={() => void tasks.refetch()} />
      ) : (
        <StaleContent stale={tasks.isPlaceholderData} className="space-y-4">
          <TaskList
            tasks={tasks.data.data}
            userId={userId}
            showProject
            empty={<TasksEmptyState filters={filters} onReset={() => changeFilters({ ...defaultTaskFilters })} />}
          />
          <Pagination meta={tasks.data.meta} onPageChange={setPage} />
        </StaleContent>
      )}
    </section>
  )
}
