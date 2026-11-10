import { Clock } from 'lucide-react'
import { Badge } from '@/components/ui/Badge'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { formatDuration } from '@/lib/duration'
import { formatDate } from '@/lib/dates'
import type { Task } from './types'

/** Status name with its configured color as a decorative dot; the text keeps AA contrast. */
export function TaskStatusLabel({ status }: { status: Task['status'] }) {
  if (!status) return null
  return (
    <span className="inline-flex items-center gap-1.5 text-xs text-text">
      <span aria-hidden="true" className="size-2 shrink-0 rounded-full" style={{ backgroundColor: status.color }} />
      {status.name}
    </span>
  )
}

/** RF-04.07 / RNF-13: overdue is said in words (badge), not only with color. */
export function TaskDue({ task }: { task: Task }) {
  if (!task.due_on) return null
  return (
    <span className={`inline-flex items-center gap-1 text-xs ${task.is_overdue ? 'font-medium text-danger' : 'text-muted'}`}>
      Vence {formatDate(task.due_on)}
      {task.is_overdue && <Badge tone="danger">Vencida</Badge>}
    </span>
  )
}

export function TaskAssigneeNames({ task }: { task: Task }) {
  const names = (task.assignees ?? []).map((assignee) => assignee.full_name)
  return <span className="text-xs text-muted">{names.length > 0 ? names.join(', ') : 'Sin asignar'}</span>
}

export const overdueRowClassName = 'border-l-4 border-l-danger'

/** Hours logged on the task (with the estimate when there is one). Hidden without the time module. */
export function TaskLoggedHours({ task }: { task: Task }) {
  const hasTime = useHasModule(MODULES.time)
  if (!hasTime || task.logged_seconds === undefined) return null
  const over = task.estimate_seconds !== null && task.logged_seconds > task.estimate_seconds
  return (
    <span className={`inline-flex items-center gap-1 text-xs ${over ? 'font-medium text-warning' : 'text-muted'}`}>
      <Clock aria-hidden="true" className="size-3.5" strokeWidth={1.75} />
      <span className="sr-only">Horas registradas:</span>
      <span className="tabular">{formatDuration(task.logged_seconds)}</span>
      {task.estimate_seconds !== null && <span className="tabular">/ {formatDuration(task.estimate_seconds)}</span>}
      {over && <span className="sr-only">(supera la estimación)</span>}
    </span>
  )
}
