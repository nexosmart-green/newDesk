import { inputClassName } from '@/components/ui/TextField'
import type { Task, TaskStatus } from './types'

interface TaskStatusSelectProps {
  code: string
  /** The task's own status: always an option, even before (or without) the company's list. */
  current: Task['status']
  statuses: TaskStatus[]
  disabled?: boolean
  onChange: (statusId: number) => void
}

/**
 * The options are the company's statuses, fetched apart from the task. Until they arrive (or if they
 * fail, or a status was created after they were cached) the task's current status would match no option
 * and the select would show blank; so the current one is always listed.
 */
function statusOptions(current: Task['status'], statuses: TaskStatus[]): Pick<TaskStatus, 'id' | 'name'>[] {
  if (!current || statuses.some((status) => status.id === current.id)) return statuses
  return [current, ...statuses]
}

/** Keyboard alternative to dragging a card between board columns. */
export function TaskStatusSelect({ code, current, statuses, disabled, onChange }: TaskStatusSelectProps) {
  return (
    <select
      aria-label={`Estado de ${code}`}
      value={current?.id ?? ''}
      disabled={disabled}
      onChange={(event) => onChange(Number(event.target.value))}
      className={`${inputClassName} py-1 text-sm`}
    >
      {!current && <option value="">Sin estado</option>}
      {statusOptions(current, statuses).map((status) => (
        <option key={status.id} value={status.id}>
          {status.name}
        </option>
      ))}
    </select>
  )
}
