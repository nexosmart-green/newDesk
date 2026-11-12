import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { useTimeDataChanged } from '@/features/timer/useTimer'
import {
  allTasksKey,
  dashboardKey,
  myTasksKey,
  projectKey,
  projectListsKey,
  projectTasksKey,
  taskActivityKey,
  taskCategoriesKey,
  taskCommentsKey,
  taskKey,
  taskLabelsKey,
  taskListsKeys,
  taskStatusesKey,
  taskTimeEntriesKey,
  timerKey,
} from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import {
  createAndStartTask,
  createTask,
  fetchAllTasks,
  fetchMyTasks,
  fetchProjectTasks,
  fetchTask,
  fetchTaskActivity,
  fetchTaskComments,
  fetchTaskStatuses,
  fetchTaskTimeEntries,
  moveTask,
  postTaskComment,
  updateTask,
  updateTaskAssignees,
} from './tasksApi'
import type { NewComment, SaveTaskInput, Task, TaskFilters } from './types'

export function useTaskStatuses() {
  return useQuery({ queryKey: taskStatusesKey, queryFn: fetchTaskStatuses, staleTime: 5 * 60_000 })
}

export function useProjectTasks(projectId: number, filters: TaskFilters) {
  return useQuery({
    queryKey: [...projectTasksKey(projectId), filters],
    queryFn: () => fetchProjectTasks(projectId, filters),
    placeholderData: keepPreviousData,
  })
}

export function useMyTasks(filters: TaskFilters) {
  return useQuery({
    queryKey: [...myTasksKey, filters],
    queryFn: () => fetchMyTasks(filters),
    placeholderData: keepPreviousData,
  })
}

export function useAllTasks(filters: TaskFilters, page: number) {
  return useQuery({
    queryKey: [...allTasksKey, filters, page],
    queryFn: () => fetchAllTasks(filters, page),
    placeholderData: keepPreviousData,
  })
}

/** Under the task's key: any task or time mutation (which invalidates `tasks`) refreshes it. */
export function useTaskTimeEntries(code: string, page: number) {
  return useQuery({
    queryKey: [...taskTimeEntriesKey(code), page],
    queryFn: () => fetchTaskTimeEntries(code, page),
    placeholderData: keepPreviousData,
  })
}

export function useTaskActivity(code: string, page: number) {
  return useQuery({
    queryKey: [...taskActivityKey(code), page],
    queryFn: () => fetchTaskActivity(code, page),
    placeholderData: keepPreviousData,
  })
}

export function useTask(code: string) {
  return useQuery({ queryKey: taskKey(code), queryFn: () => fetchTask(code) })
}

export function useTaskComments(code: string) {
  return useQuery({ queryKey: taskCommentsKey(code), queryFn: () => fetchTaskComments(code) })
}

interface TaskRefresh {
  /** The server's version of the task: written to its detail at once (that query is not refetched). */
  task?: Task
  /** Projects whose detail shows the task (both of them on a move). */
  projectIds?: readonly (number | undefined)[]
  /** Category/label changes move their `tasks_count`. */
  catalogs?: boolean
  /** What the screen in front of the person shows; the rest refreshes in the background. */
  now?: readonly (readonly unknown[])[]
}

/**
 * Lists, board and the Panel read task rows, the task's Historial logs the change, and the projects that
 * hold it show their tasks. Its other tabs (comments, files) don't change.
 */
export function useRefreshTasks() {
  const queryClient = useQueryClient()
  return ({ task, projectIds = [], catalogs = false, now = [] }: TaskRefresh = {}) => {
    if (task) queryClient.setQueryData<Task>(taskKey(task.code), (current) => ({ ...current, ...task }))
    const projects = [...new Set([...projectIds, task?.project?.id].filter((id): id is number => id !== undefined))]
    return refreshQueries(queryClient, {
      now,
      later: [
        ...taskListsKeys,
        ...(task ? [taskActivityKey(task.code)] : []),
        dashboardKey,
        projectListsKey,
        ...projects.map(projectKey),
        ...(catalogs ? [taskCategoriesKey, taskLabelsKey] : []),
      ],
    })
  }
}

export function useCreateTask(projectId: number) {
  const refresh = useRefreshTasks()
  return useMutation({
    mutationFn: (input: SaveTaskInput) => createTask(projectId, input),
    onSuccess: (task) => {
      toast.success(`Tarea ${task.code} creada.`)
      // The dialog closes onto the project's list: it shows the new task.
      return refresh({ projectIds: [projectId], catalogs: true, now: [projectTasksKey(projectId)] })
    },
  })
}

export function useCreateAndStartTask(projectId: number) {
  const refresh = useRefreshTasks()
  const timeChanged = useTimeDataChanged()
  return useMutation({
    // The dialog asks for the running timer's comment and explains a timer that didn't start.
    meta: { silentError: true },
    mutationFn: ({ input, previousComment }: { input: SaveTaskInput; previousComment?: string }) =>
      createAndStartTask(projectId, input, previousComment),
    onSuccess: (result) => {
      if (!result.timer_error) toast.success(`Tarea ${result.task.code} creada y cronómetro en marcha.`)
      return Promise.all([
        refresh({ projectIds: [projectId], catalogs: true, now: [projectTasksKey(projectId)] }),
        timeChanged([timerKey]),
      ])
    },
  })
}

export function useUpdateTask(code: string) {
  const refresh = useRefreshTasks()
  return useMutation({
    meta: { successMessage: 'Tarea actualizada.' },
    mutationFn: (input: Partial<Omit<SaveTaskInput, 'assignee_ids'>>) => updateTask(code, input),
    onSuccess: (task) => refresh({ task, catalogs: true }),
  })
}

export function useUpdateTaskAssignees(code: string) {
  const refresh = useRefreshTasks()
  return useMutation({
    mutationFn: (assigneeIds: number[]) => updateTaskAssignees(code, assigneeIds),
    meta: { successMessage: 'Asignación actualizada.' },
    onSuccess: (task) => refresh({ task }),
  })
}

export function usePostTaskComment(code: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (comment: NewComment) => postTaskComment(code, comment),
    meta: { successMessage: 'Comentario publicado.' },
    // The composer clears once the new comment is in the list above it.
    onSuccess: () => refreshQueries(queryClient, { now: [taskCommentsKey(code)], later: [taskActivityKey(code)] }),
  })
}

/** Moving changes both projects' hours, so lists, bags and timesheets refresh. */
export function useMoveTask(code: string) {
  const queryClient = useQueryClient()
  const refresh = useRefreshTasks()
  const timeChanged = useTimeDataChanged()
  return useMutation({
    mutationFn: (projectId: number) => moveTask(code, projectId),
    onSuccess: (result) => {
      const from = queryClient.getQueryData<Task>(taskKey(code))?.project?.id
      return Promise.all([refresh({ task: result.task, projectIds: [from] }), timeChanged()])
    },
  })
}
