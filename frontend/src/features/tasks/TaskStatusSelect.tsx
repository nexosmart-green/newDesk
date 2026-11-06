import { inputClassName } from '@/components/ui/TextField'
import type { TaskStatus } from './types'

interface TaskStatusSelectProps {
  code: string
  statusId: number | undefined
  statuses: TaskStatus[]
  disabled?: boolean
  onChange: (statusId: number) => void
}

/** Keyboard alternative to dragging a card between board columns. */
export function TaskStatusSelect({ code, statusId, statuses, disabled, onChange }: TaskStatusSelectProps) {
  return (
    <select
      aria-label={`Estado de ${code}`}
      value={statusId ?? ''}
      disabled={disabled}
      onChange={(event) => onChange(Number(event.target.value))}
      className={`${inputClassName} py-1 text-sm`}
    >
      {statuses.map((status) => (
        <option key={status.id} value={status.id}>
          {status.name}
        </option>
      ))}
    </select>
  )
}
