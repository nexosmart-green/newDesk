import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTimeDataChanged } from '@/features/timer/useTimer'
import {
  createAndStartTask,
  createTask,
  fetchMyTasks,
  fetchProjectTasks,
  fetchTask,
  fetchTaskComments,
  fetchTaskStatuses,
  postTaskComment,
  updateTask,
  updateTaskAssignees,
  updateTaskStatus,
} from './tasksApi'
import type { NewComment, SaveTaskInput, Task, TaskFilters } from './types'

export const tasksKey = ['tasks'] as const
const taskKey = (code: string) => [...tasksKey, 'detail', code.toLowerCase()] as const
const commentsKey = (code: string) => [...taskKey(code), 'comments'] as const

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

export function useTask(code: string) {
  return useQuery({ queryKey: taskKey(code), queryFn: () => fetchTask(code) })
}

export function useTaskComments(code: string) {
  return useQuery({ queryKey: commentsKey(code), queryFn: () => fetchTaskComments(code) })
}

/** Lists, board and detail share the task rows; the activity log changes too. */
function useRefreshTasks() {
  const queryClient = useQueryClient()
  return (task?: Task) => {
    if (task) queryClient.setQueryData<Task>(taskKey(task.code), (current) => ({ ...current, ...task }))
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: tasksKey }),
      queryClient.invalidateQueries({ queryKey: ['projects'] }),
    ])
  }
}

export function useCreateTask(projectId: number) {
  const refresh = useRefreshTasks()
  return useMutation({ mutationFn: (input: SaveTaskInput) => createTask(projectId, input), onSuccess: () => refresh() })
}

export function useCreateAndStartTask(projectId: number) {
  const refresh = useRefreshTasks()
  const timeChanged = useTimeDataChanged()
  return useMutation({
    mutationFn: ({ input, previousComment }: { input: SaveTaskInput; previousComment?: string }) =>
      createAndStartTask(projectId, input, previousComment),
    onSuccess: () => Promise.all([refresh(), timeChanged()]),
  })
}

export function useUpdateTask(code: string) {
  const refresh = useRefreshTasks()
  return useMutation({
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
    onSuccess: (task) => Promise.all([refresh(task), timeChanged()]),
  })
}

export function useUpdateTaskAssignees(code: string) {
  const refresh = useRefreshTasks()
  return useMutation({ mutationFn: (assigneeIds: number[]) => updateTaskAssignees(code, assigneeIds), onSuccess: refresh })
}

export function usePostTaskComment(code: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (comment: NewComment) => postTaskComment(code, comment),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: commentsKey(code) }),
  })
}
