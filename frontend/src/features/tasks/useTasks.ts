import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { useTimeDataChanged } from '@/features/timer/useTimer'
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
  updateTaskStatus,
} from './tasksApi'
import type { NewComment, SaveTaskInput, Task, TaskFilters } from './types'

export const tasksKey = ['tasks'] as const
export const taskKey = (code: string) => [...tasksKey, 'detail', code.toLowerCase()] as const
const commentsKey = (code: string) => [...taskKey(code), 'comments'] as const
/** Counters and "próximas a vencer" of the Panel read task rows too. */
export const dashboardKey = ['dashboard'] as const

export function useTaskStatuses() {
  return useQuery({ queryKey: ['task-statuses'], queryFn: fetchTaskStatuses, staleTime: 5 * 60_000 })
}

export function useProjectTasks(projectId: number, filters: TaskFilters) {
  return useQuery({
    queryKey: [...tasksKey, 'project', projectId, filters],
    queryFn: () => fetchProjectTasks(projectId, filters),
    placeholderData: keepPreviousData,
  })
}

export function useMyTasks(filters: TaskFilters) {
  return useQuery({
    queryKey: [...tasksKey, 'mine', filters],
    queryFn: () => fetchMyTasks(filters),
    placeholderData: keepPreviousData,
  })
}

export function useAllTasks(filters: TaskFilters, page: number) {
  return useQuery({
    queryKey: [...tasksKey, 'all', filters, page],
    queryFn: () => fetchAllTasks(filters, page),
    placeholderData: keepPreviousData,
  })
}

/** Under the task's key: any task or time mutation (which invalidates `tasks`) refreshes it. */
export function useTaskTimeEntries(code: string, page: number) {
  return useQuery({
    queryKey: [...taskKey(code), 'time-entries', page],
    queryFn: () => fetchTaskTimeEntries(code, page),
    placeholderData: keepPreviousData,
  })
}

export function useTaskActivity(code: string, page: number) {
  return useQuery({
    queryKey: [...taskKey(code), 'activity', page],
    queryFn: () => fetchTaskActivity(code, page),
    placeholderData: keepPreviousData,
  })
}

export function useTask(code: string) {
  return useQuery({ queryKey: taskKey(code), queryFn: () => fetchTask(code) })
}

export function useTaskComments(code: string) {
  return useQuery({ queryKey: commentsKey(code), queryFn: () => fetchTaskComments(code) })
}

/** Lists, board, detail and the Panel share the task rows; the activity log changes too. */
export function useRefreshTasks() {
  const queryClient = useQueryClient()
  return (task?: Task) => {
    if (task) queryClient.setQueryData<Task>(taskKey(task.code), (current) => ({ ...current, ...task }))
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: tasksKey }),
      queryClient.invalidateQueries({ queryKey: ['projects'] }),
      queryClient.invalidateQueries({ queryKey: dashboardKey }),
      // Their `tasks_count` changes with a task's category/labels (literal keys: importing them would be circular).
      queryClient.invalidateQueries({ queryKey: ['task-categories'] }),
      queryClient.invalidateQueries({ queryKey: ['task-labels'] }),
    ])
  }
}

export function useCreateTask(projectId: number) {
  const refresh = useRefreshTasks()
  return useMutation({
    mutationFn: (input: SaveTaskInput) => createTask(projectId, input),
    onSuccess: (task) => {
      toast.success(`Tarea ${task.code} creada.`)
      return refresh()
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
      return Promise.all([refresh(), timeChanged()])
    },
  })
}

export function useUpdateTask(code: string) {
  const refresh = useRefreshTasks()
  return useMutation({
    meta: { successMessage: 'Tarea actualizada.' },
    mutationFn: (input: Partial<Omit<SaveTaskInput, 'assignee_ids'>>) => updateTask(code, input),
    onSuccess: refresh,
  })
}

/** CA-04.03: closing a task stops its timers, so the header refreshes too. */
export function useUpdateTaskStatus() {
  const refresh = useRefreshTasks()
  const timeChanged = useTimeDataChanged()
  return useMutation({
    mutationFn: ({ code, statusId }: { code: string; statusId: number }) => updateTaskStatus(code, statusId),
    meta: { successMessage: 'Estado actualizado.' },
    onSuccess: (task) => Promise.all([refresh(task), timeChanged()]),
  })
}

export function useUpdateTaskAssignees(code: string) {
  const refresh = useRefreshTasks()
  return useMutation({
    mutationFn: (assigneeIds: number[]) => updateTaskAssignees(code, assigneeIds),
    meta: { successMessage: 'Asignación actualizada.' },
    onSuccess: refresh,
  })
}

export function usePostTaskComment(code: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (comment: NewComment) => postTaskComment(code, comment),
    meta: { successMessage: 'Comentario publicado.' },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: commentsKey(code) }),
  })
}

/** Moving changes both projects' hours, so lists, bags and timesheets refresh. */
export function useMoveTask(code: string) {
  const refresh = useRefreshTasks()
  const timeChanged = useTimeDataChanged()
  return useMutation({
    mutationFn: (projectId: number) => moveTask(code, projectId),
    onSuccess: (result) => Promise.all([refresh(result.task), timeChanged()]),
  })
}
