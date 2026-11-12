import type { AvatarUrls } from '@/features/people/types'
import type { EntrySource, TimeEntry } from '@/features/timer/types'
import type { TimedPage } from '@/lib/api'

/** RF-04.03: what the rules read from a configurable status. Closed ones are hidden by default. */
type StatusKind = 'open' | 'blocked' | 'closed'

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

interface TaskAssignee {
  id: number
  full_name: string
  avatar: AvatarUrls | null
}

/** Company catalog (Configuración → Catálogos). One per task. */
export interface TaskCategory {
  id: number
  name: string
  /** Only in the catalog listing. */
  tasks_count?: number
}

/** Company catalog with a `#rrggbb` color. N per task. */
export interface TaskLabel {
  id: number
  name: string
  color: string
  tasks_count?: number
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
  /** Sum of the task's entries, the running one counted up to now (server-computed). */
  logged_seconds?: number
  category?: Pick<TaskCategory, 'id' | 'name'> | null
  labels?: TaskLabel[]
  /** Only in the task detail (`GET /tasks/{code}`). Markdown (GFM). */
  description?: string | null
  created_by?: { id: number; full_name: string } | null
  created_at?: string
  /** RF-04.08, lists and detail: "3/5" on the row. */
  checklist_progress?: ChecklistProgress
  /** Only in the task detail, ordered by `position`. */
  checklist?: ChecklistItem[]
  /** RF-04.09, lists and detail: the clip on the row. */
  attachments_count?: number
}

export interface ChecklistProgress {
  done: number
  total: number
}

export interface ChecklistItem {
  id: number
  text: string
  position: number
  done: boolean
  done_at: string | null
  done_by: { id: number; full_name: string } | null
}

export interface TaskAttachment {
  id: number
  uuid: string
  name: string
  mime: string
  size: number
  /** Relative, same-origin (session cookie): images and PDF open inline, the rest downloads. */
  url: string
  is_image: boolean
  uploaded_by: { id: number; full_name: string } | null
  created_at: string
  can_delete: boolean
}

/** RF-04.05. Empty `status_ids` + `include_closed: false` = every non-closed status (default). */
export interface TaskFilters {
  status_ids: number[]
  include_closed: boolean
  assignee_id: number | null
  overdue: boolean
  search: string
  category_id: number | null
  label_id: number | null
  /** Only "Todas las tareas" offers it (the other lists are already one project, or mine). */
  project_id: number | null
}

export const defaultTaskFilters: TaskFilters = {
  status_ids: [],
  include_closed: false,
  assignee_id: null,
  overdue: false,
  search: '',
  category_id: null,
  label_id: null,
  project_id: null,
}

export interface SaveTaskInput {
  title: string
  description: string | null
  priority: TaskPriority
  starts_on: string | null
  due_on: string | null
  estimate_seconds: number | null
  category_id: number | null
  label_ids: number[]
  assignee_ids: number[]
}

interface TimerError {
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

/** One worked stretch of a task (tab "Horas"). Never carries rates. */
export interface TaskTimeEntry {
  id: number
  user: TaskAssignee
  started_at: string
  ended_at: string | null
  seconds: number
  is_running: boolean
  comment: string | null
  comment_pending: boolean
  source: EntrySource
}

/** `meta.total_seconds` is the whole task's total, not the page's. */
export type TaskTimeEntriesPage = TimedPage<TaskTimeEntry>
