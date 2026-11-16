import { CalendarCheck } from 'lucide-react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { EmptyState } from '@/components/ui/EmptyState'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { TaskCode } from '@/features/tasks/TaskCode'
import type { Task } from '@/features/tasks/types'
import { formatDate } from '@/lib/dates'
import { todayIn } from '@/lib/timezone'
import { dueText } from './dueText'

/** My next deadlines (max 5, soonest first), straight to each task. */
export function DueSoonList({ tasks }: { tasks: Task[] }) {
  const today = todayIn(useProfileTimeZone())

  return (
    <section aria-labelledby="due-soon-title" className="flex flex-col rounded-lg border border-line bg-surface p-4 sm:p-5">
      <h2 id="due-soon-title" className="text-base font-semibold">
        Próximas a vencer
      </h2>
      {tasks.length === 0 ? (
        <EmptyState
          icon={CalendarCheck}
          bare
          className="mt-2"
          action={
            <Link to="/mis-tareas" className="text-sm text-accent hover:underline">
              Ver mis tareas
            </Link>
          }
        >
          No tenés tareas abiertas con vencimiento.
        </EmptyState>
      ) : (
        <ul className="-mx-2 mt-2 space-y-0.5">
          {tasks.map((task) => (
            <li key={task.id}>
              <Link
                to={`/tareas/${task.code}`}
                className="flex flex-col gap-0.5 rounded-md px-2 py-2 transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent"
              >
                <span className="truncate text-sm font-medium">
                  <TaskCode code={task.code} className="mr-1.5" />
                  {task.title}
                </span>
                <span className="flex flex-wrap items-center gap-2 text-xs text-muted">
                  {task.project && <span className="truncate">{task.project.name}</span>}
                  {task.due_on && (
                    <span className={task.is_overdue ? 'font-medium text-danger' : ''}>
                      {task.is_overdue ? `Venció ${formatDate(task.due_on)}` : dueText(task.due_on, today)}
                    </span>
                  )}
                  {task.is_overdue && <Badge tone="danger">Vencida</Badge>}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
