import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { useCurrentUser } from '@/features/auth/useAuth'
import { useProjectFromOutlet } from '@/features/projects/projectContext'
import { isClosedStatus } from '@/features/projects/types'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { CreateTaskDialog } from './CreateTaskDialog'
import { TaskBoard } from './TaskBoard'
import { TaskFiltersBar } from './TaskFiltersBar'
import { TaskList } from './TaskList'
import { useProjectTasks, useTaskStatuses } from './useTasks'
import { useTaskFilters } from './useTaskFilters'

type TasksView = 'list' | 'board'

const views: { id: TasksView; label: string }[] = [
  { id: 'list', label: 'Lista' },
  { id: 'board', label: 'Tablero' },
]

/** RF-04.04/05: list and board over the same query and filters. */
export function ProjectTasksTab() {
  const project = useProjectFromOutlet()
  const userId = useCurrentUser().data?.id
  const { filters, change } = useTaskFilters('project')
  const [view, setView] = useState<TasksView>('list')
  const [creating, setCreating] = useState(false)
  const statuses = useTaskStatuses()
  const tasks = useProjectTasks(project.id, { ...filters, search: useDebouncedValue(filters.search) })
  const canCreate = !isClosedStatus(project.status)

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div role="group" aria-label="Vista" className="inline-flex rounded border border-border">
          {views.map((option) => (
            <button
              key={option.id}
              type="button"
              aria-pressed={view === option.id}
              onClick={() => setView(option.id)}
              className={`px-3 py-1.5 text-sm ${view === option.id ? 'bg-accent text-white' : 'text-muted hover:text-text'}`}
            >
              {option.label}
            </button>
          ))}
        </div>
        {canCreate && <Button onClick={() => setCreating(true)}>Nueva tarea</Button>}
      </div>
      <TaskFiltersBar filters={filters} statuses={statuses.data ?? []} members={project.members} onChange={change} />
      {!tasks.data || !statuses.data ? (
        <PageState isPending={tasks.isPending || statuses.isPending} error={tasks.error ?? statuses.error} />
      ) : view === 'list' ? (
        <TaskList tasks={tasks.data.data} userId={userId} />
      ) : (
        <TaskBoard tasks={tasks.data.data} statuses={statuses.data} filters={filters} projectId={project.id} userId={userId} />
      )}
      {tasks.data && tasks.data.meta.total > tasks.data.data.length && (
        <p className="text-xs text-muted">
          Se muestran {tasks.data.data.length} de {tasks.data.meta.total} tareas: afiná los filtros para ver el resto.
        </p>
      )}
      {creating && <CreateTaskDialog projectId={project.id} members={project.members} onClose={() => setCreating(false)} />}
    </div>
  )
}
