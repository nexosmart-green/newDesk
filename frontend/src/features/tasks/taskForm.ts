import type { SaveTaskInput, Task, TaskPriority } from './types'

/** What the create/edit forms hold: strings as typed, converted once on submit. */
export interface TaskFormValues {
  title: string
  description: string
  priority: TaskPriority
  starts_on: string
  due_on: string
  /** Hours as typed ("1.5"); stored as integer seconds (R-5). */
  estimate_hours: string
}

export const emptyTaskForm: TaskFormValues = {
  title: '',
  description: '',
  priority: 'medium',
  starts_on: '',
  due_on: '',
  estimate_hours: '',
}

function hoursOf(seconds: number | null): string {
  return seconds === null ? '' : String(Math.round((seconds / 3600) * 100) / 100)
}

export function taskFormValuesOf(task: Task): TaskFormValues {
  return {
    title: task.title,
    description: task.description ?? '',
    priority: task.priority,
    starts_on: task.starts_on ?? '',
    due_on: task.due_on ?? '',
    estimate_hours: hoursOf(task.estimate_seconds),
  }
}

export function estimateSecondsOf(hours: string): number | null {
  const parsed = Number(hours.replace(',', '.'))
  return hours.trim() === '' || !Number.isFinite(parsed) || parsed < 0 ? null : Math.round(parsed * 3600)
}

export function toSaveTaskInput(values: TaskFormValues): Omit<SaveTaskInput, 'assignee_ids'> {
  return {
    title: values.title.trim(),
    description: values.description.trim() || null,
    priority: values.priority,
    starts_on: values.starts_on || null,
    due_on: values.due_on || null,
    estimate_seconds: estimateSecondsOf(values.estimate_hours),
  }
}
