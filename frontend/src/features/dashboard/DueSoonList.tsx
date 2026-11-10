import { CalendarCheck } from 'lucide-react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import type { Task } from '@/features/tasks/types'
import { formatDate } from '@/lib/dates'
import { todayIn } from '@/lib/timezone'
import { dueText } from './dueText'

/** My next deadlines (max 5, soonest first), straight to each task. */
export function DueSoonList({ tasks }: { tasks: Task[] }) {
  const today = todayIn(useProfileTimeZone())

  return (
    <section aria-labelledby="due-soon-title" className="flex flex-col rounded-xl border border-border bg-surface p-4 sm:p-5">
      <h2 id="due-soon-title" className="font-semibold">
        Próximas a vencer
      </h2>
      {tasks.length === 0 ? (
        <div className="flex flex-1 flex-col items-start justify-center gap-2 py-6">
          <CalendarCheck aria-hidden="true" className="size-6 text-muted" />
          <p className="text-sm text-muted">No tenés tareas abiertas con vencimiento.</p>
          <Link to="/mis-tareas" className="text-sm text-accent hover:underline">
            Ver mis tareas
          </Link>
        </div>
      ) : (
        <ul className="-mx-2 mt-2 space-y-0.5">
          {tasks.map((task) => (
            <li key={task.id}>
              <Link
                to={`/tareas/${task.code}`}
                className="flex flex-col gap-0.5 rounded-lg px-2 py-2 transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent"
              >
                <span className="truncate text-sm font-medium">
                  <span className="mr-1.5 font-mono text-xs uppercase text-muted">{task.code}</span>
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
