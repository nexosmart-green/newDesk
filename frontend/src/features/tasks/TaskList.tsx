import { Link } from 'react-router'
import { StartTimerButton } from '@/features/timer/StartTimerButton'
import { overdueRowClassName, TaskAssigneeNames, TaskDue, TaskStatusLabel } from './TaskBits'
import { isAssignedTo, type Task } from './types'

interface TaskListProps {
  tasks: Task[]
  userId: number | undefined
  /** "Mis tareas" mixes projects, so each row says which. */
  showProject?: boolean
}

/** RF-04.04 "Lista": one row per task, stacked as a card on mobile. */
export function TaskList({ tasks, userId, showProject = false }: TaskListProps) {
  if (tasks.length === 0) return <p className="text-sm text-muted">No hay tareas que coincidan con los filtros.</p>

  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Tareas">
      {tasks.map((task) => (
        <li
          key={task.id}
          className={`flex items-start gap-3 p-3 ${task.is_overdue ? overdueRowClassName : ''}`}
          aria-label={task.is_overdue ? `${task.code} (vencida)` : undefined}
        >
          <div className="min-w-0 flex-1 space-y-1">
            <Link to={`/tareas/${task.code}`} className="block hover:underline">
              <span className="mr-2 font-mono text-xs uppercase text-muted">{task.code}</span>
              <span className="font-medium">{task.title}</span>
            </Link>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
              {showProject && task.project && <span className="text-xs text-muted">{task.project.name}</span>}
              <TaskStatusLabel status={task.status} />
              <TaskAssigneeNames task={task} />
              <TaskDue task={task} />
            </div>
          </div>
          {isAssignedTo(task, userId) && <StartTimerButton task={task} compact />}
        </li>
      ))}
    </ul>
  )
}
