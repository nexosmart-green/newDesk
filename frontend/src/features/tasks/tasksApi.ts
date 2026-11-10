import type { ActivityEntry } from '@/features/projects/types'
import { api, dataOf, pageOf, queryParams, timedPageOf, type ApiEnvelope, type Paginated } from '@/lib/api'
import type {
  CreateAndStartResult,
  NewComment,
  SaveTaskInput,
  Task,
  TaskComment,
  TaskFilters,
  TaskStatus,
  TaskTimeEntriesPage,
} from './types'

/** The board needs every card of the project at once; the backend caps it at 200. */
export const TASKS_PER_PAGE = 200

/** "Todas las tareas" pages through every visible project instead. */
export const ALL_TASKS_PER_PAGE = 50

function taskListParams(filters: TaskFilters, perPage = TASKS_PER_PAGE) {
  const { status_ids: statusIds, ...rest } = filters
  return {
    ...queryParams({ ...rest }),
    ...(statusIds.length > 0 ? { status_ids: statusIds } : {}),
    per_page: perPage,
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

/** Every task of the projects the viewer can see (same visibility rule as /proyectos). */
export async function fetchAllTasks(filters: TaskFilters, page: number): Promise<Paginated<Task>> {
  return pageOf(api.get<Paginated<Task>>('/tasks', { params: { ...taskListParams(filters, ALL_TASKS_PER_PAGE), page } }))
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

/** Tab "Horas": newest first, 50 per page; `meta.total_seconds` is the whole task. */
export async function fetchTaskTimeEntries(code: string, page: number): Promise<TaskTimeEntriesPage> {
  return timedPageOf(api.get<TaskTimeEntriesPage>(`${taskPath(code)}/time-entries`, { params: { page } }))
}

/** Tab "Historial": the project activity log filtered to this task. */
export async function fetchTaskActivity(code: string, page: number): Promise<Paginated<ActivityEntry>> {
  return pageOf(api.get<Paginated<ActivityEntry>>(`${taskPath(code)}/activity`, { params: { page } }))
}

/** Markdown images: uploaded to the project, referenced by the relative URL the backend returns. */
export async function uploadTaskImage(projectId: number, file: File, onProgress: (percent: number) => void): Promise<string> {
  const body = new FormData()
  body.append('image', file)
  const result = await dataOf(
    api.post<ApiEnvelope<{ url: string }>>(`/projects/${projectId}/task-images`, body, {
      onUploadProgress: (event) => {
        if (event.total) onProgress(Math.round((event.loaded / event.total) * 100))
      },
    }),
  )
  return result.url
}
