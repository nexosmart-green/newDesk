import type { AvatarUrls } from '@/features/people/types'
import type { TimeEntry } from '@/features/timer/types'

/** RF-04.03: what the rules read from a configurable status. Closed ones are hidden by default. */
export type StatusKind = 'open' | 'blocked' | 'closed'

export interface TaskStatus {
  id: number
  name: string
  color: string
  position: number
  kind: StatusKind
}

export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export const taskPriorityLabels: Record<TaskPriority, string> = {
  low: 'Baja',
  medium: 'Media',
  high: 'Alta',
  urgent: 'Urgente',
}

export const taskPriorities = Object.keys(taskPriorityLabels) as TaskPriority[]

export function isTaskPriority(value: unknown): value is TaskPriority {
  return typeof value === 'string' && (taskPriorities as string[]).includes(value)
}

export interface TaskAssignee {
  id: number
  full_name: string
  avatar: AvatarUrls | null
}

export interface Task {
  id: number
  code: string
  title: string
  /** `hours_exhausted`: the project's hour bag is spent, so ▶ can't start (RF-05.05). */
  project?: { id: number; name: string; code_prefix: string; hours_exhausted: boolean }
  status?: Omit<TaskStatus, 'position'>
  priority: TaskPriority
  starts_on: string | null
  due_on: string | null
  is_overdue: boolean
  estimate_seconds: number | null
  completed_at: string | null
  assignees?: TaskAssignee[]
  /** Only in the task detail (`GET /tasks/{code}`). */
  description?: string | null
}

/** RF-04.05. Empty `status_ids` + `include_closed: false` = every non-closed status (default). */
export interface TaskFilters {
  status_ids: number[]
  include_closed: boolean
  assignee_id: number | null
  overdue: boolean
  search: string
}

export const defaultTaskFilters: TaskFilters = {
  status_ids: [],
  include_closed: false,
  assignee_id: null,
  overdue: false,
  search: '',
}

export interface SaveTaskInput {
  title: string
  description: string | null
  priority: TaskPriority
  starts_on: string | null
  due_on: string | null
  estimate_seconds: number | null
  assignee_ids: number[]
}

export interface TimerError {
  code: string
  message: string
  project_id?: number
  available_seconds?: number
}

/** RF-04.02: the task can exist while the timer didn't start (CA-04.02b). */
export interface CreateAndStartResult {
  task: Task
  time_entry: TimeEntry | null
  timer_error: TimerError | null
}

export interface TaskComment {
  id: number
  body: string
  author: { id: number; full_name: string }
  mentions: { id: number; full_name: string }[]
  created_at: string | null
}

export interface NewComment {
  body: string
  mentioned_user_ids: number[]
}

export function isAssignedTo(task: Task, userId: number | undefined): boolean {
  return userId !== undefined && (task.assignees ?? []).some((assignee) => assignee.id === userId)
}
