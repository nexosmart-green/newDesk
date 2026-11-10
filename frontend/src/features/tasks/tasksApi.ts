import { api, dataOf, pageOf, queryParams, type ApiEnvelope, type Paginated } from '@/lib/api'
import type {
  CreateAndStartResult,
  NewComment,
  SaveTaskInput,
  Task,
  TaskComment,
  TaskFilters,
  TaskStatus,
} from './types'

/** The board needs every card of the project at once; the backend caps it at 200. */
export const TASKS_PER_PAGE = 200

function taskListParams(filters: TaskFilters) {
  const { status_ids: statusIds, ...rest } = filters
  return {
    ...queryParams({ ...rest }),
    ...(statusIds.length > 0 ? { status_ids: statusIds } : {}),
    per_page: TASKS_PER_PAGE,
  }
}

const taskPath = (code: string) => `/tasks/${encodeURIComponent(code)}`

export async function fetchTaskStatuses(): Promise<TaskStatus[]> {
  return dataOf(api.get<ApiEnvelope<TaskStatus[]>>('/task-statuses'))
}

export async function fetchProjectTasks(projectId: number, filters: TaskFilters): Promise<Paginated<Task>> {
  return pageOf(api.get<Paginated<Task>>(`/projects/${projectId}/tasks`, { params: taskListParams(filters) }))
}

export async function fetchMyTasks(filters: TaskFilters): Promise<Paginated<Task>> {
  return pageOf(api.get<Paginated<Task>>('/me/tasks', { params: taskListParams(filters) }))
}

export async function fetchTask(code: string): Promise<Task> {
  return dataOf(api.get<ApiEnvelope<Task>>(taskPath(code)))
}

export async function createTask(projectId: number, input: SaveTaskInput): Promise<Task> {
  return dataOf(api.post<ApiEnvelope<Task>>(`/projects/${projectId}/tasks`, input))
}

/** RF-04.02. A 422 `previous_comment_required` creates nothing; other timer failures come back in `timer_error`. */
export async function createAndStartTask(
  projectId: number,
  input: SaveTaskInput,
  previousComment?: string,
): Promise<CreateAndStartResult> {
  const body = previousComment ? { ...input, previous_comment: previousComment } : input
  return dataOf(api.post<ApiEnvelope<CreateAndStartResult>>(`/projects/${projectId}/tasks/start`, body))
}

export async function updateTask(code: string, input: Partial<Omit<SaveTaskInput, 'assignee_ids'>>): Promise<Task> {
  return dataOf(api.put<ApiEnvelope<Task>>(taskPath(code), input))
}

export async function updateTaskStatus(code: string, statusId: number): Promise<Task> {
  return dataOf(api.put<ApiEnvelope<Task>>(`${taskPath(code)}/status`, { status_id: statusId }))
}

export async function updateTaskAssignees(code: string, assigneeIds: number[]): Promise<Task> {
  return dataOf(api.put<ApiEnvelope<Task>>(`${taskPath(code)}/assignees`, { assignee_ids: assigneeIds }))
}

export async function fetchTaskComments(code: string): Promise<Paginated<TaskComment>> {
  return pageOf(api.get<Paginated<TaskComment>>(`${taskPath(code)}/comments`))
}

export async function postTaskComment(code: string, comment: NewComment): Promise<TaskComment> {
  return dataOf(api.post<ApiEnvelope<TaskComment>>(`${taskPath(code)}/comments`, comment))
}

export interface MoveTaskResult {
  task: Task
  seconds_moved: number
  /** People removed from the task because they are not members of the destination (their hours stay). */
  unassigned: string[]
}

/** RF-04.12: move to another project; its hours move with it and the old code keeps working. */
export async function moveTask(code: string, projectId: number): Promise<MoveTaskResult> {
  return dataOf(api.put<ApiEnvelope<MoveTaskResult>>(`${taskPath(code)}/project`, { project_id: projectId }))
}
