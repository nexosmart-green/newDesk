import type { ReactNode } from 'react'
import { SearchX } from 'lucide-react'
import { Link } from 'react-router'
import { EmptyState } from '@/components/ui/EmptyState'
import { CategoryTag, LabelChips } from '@/features/taskCatalogs/LabelChip'
import { StartTimerButton } from '@/features/timer/StartTimerButton'
import { useRunningTimer } from '@/features/timer/useRunningTimer'
import { overdueRowClassName, TaskAttachmentsBadge, TaskChecklistBadge, TaskAssigneeNames, TaskDue, TaskLoggedHours, TaskStatusLabel } from './TaskBits'
import { TaskCode } from './TaskCode'
import { isAssignedTo, type Task } from './types'

interface TaskListProps {
  tasks: Task[]
  userId: number | undefined
  /** "Mis tareas" and "Todas las tareas" mix projects, so each row says which. */
  showProject?: boolean
  /** Shown instead of the list when nothing matches (an action helps more than a dead end). */
  empty?: ReactNode
}

/** RF-04.04 "Lista": one row per task, stacked as a card on mobile. */
export function TaskList({ tasks, userId, showProject = false, empty }: TaskListProps) {
  const runningCode = useRunningTimer()?.code.toLowerCase()
  if (tasks.length === 0) return empty ?? <EmptyState icon={SearchX}>No hay tareas que coincidan con los filtros.</EmptyState>

  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Tareas">
      {tasks.map((task) => (
        <li
          key={task.id}
          // The task being timed right now: a pink rule (desk's "running now"), above the overdue mark.
          className={`flex items-start gap-3 p-3 ${task.code.toLowerCase() === runningCode ? 'shadow-[inset_3px_0_0_var(--color-live)]' : task.is_overdue ? overdueRowClassName : ''}`}
          aria-label={task.is_overdue ? `${task.code} (vencida)` : undefined}
        >
          <div className="min-w-0 flex-1 space-y-1.5">
            <Link to={`/tareas/${task.code}`} className="block hover:underline">
              <TaskCode code={task.code} className="mr-2" />
              <span className="font-medium">{task.title}</span>
            </Link>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
              {showProject && task.project && <span className="text-xs text-muted">{task.project.name}</span>}
              <TaskStatusLabel status={task.status} />
              <CategoryTag category={task.category} />
              <TaskAssigneeNames task={task} />
              <TaskDue task={task} />
              <TaskLoggedHours task={task} />
              <TaskChecklistBadge task={task} />
              <TaskAttachmentsBadge task={task} />
            </div>
            <LabelChips labels={task.labels} />
          </div>
          {isAssignedTo(task, userId) && <StartTimerButton task={task} compact />}
        </li>
      ))}
    </ul>
  )
}
