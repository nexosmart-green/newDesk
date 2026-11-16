import { hashKey, useMutation, useMutationState, useQueryClient, type Query, type QueryClient } from '@tanstack/react-query'
import { useTimeDataChanged } from '@/features/timer/useTimer'
import { taskKey, tasksKey, taskStatusesKey, taskStatusMutationKey } from '@/lib/queryKeys'
import { updateTaskStatus } from './tasksApi'
import type { Task, TaskStatus } from './types'
import { useRefreshTasks } from './useTasks'

interface StatusChange {
  code: string
  statusId: number
}

type TaskPage = { data: Task[] }
type CachedTasks = TaskPage | Task
type RowStatus = Task['status']

const sameCode = (task: Task, code: string) => task.code.toLowerCase() === code.toLowerCase()

/** Every cached list holding the task, plus its own detail (not the details' tabs). */
function cachesOf(code: string) {
  const detailHash = hashKey(taskKey(code))
  return { queryKey: tasksKey, predicate: (query: Query) => query.queryKey[1] !== 'detail' || query.queryHash === detailHash }
}

/** Sets one task's status wherever it is cached; returns the status it had (for the way back). */
function writeStatus(queryClient: QueryClient, code: string, status: RowStatus): RowStatus {
  const caches = cachesOf(code)
  let previous: RowStatus
  const withStatus = (task: Task) => {
    if (!sameCode(task, code)) return task
    previous ??= task.status
    return { ...task, status }
  }
  queryClient.setQueriesData<CachedTasks>(caches, (cached) => {
    if (!cached) return cached
    return 'data' in cached ? { ...cached, data: cached.data.map(withStatus) } : withStatus(cached)
  })
  return previous
}

/**
 * CA-04.03: closing a task stops its timers, so the header refreshes too. Optimistic: the card or row shows
 * the new status at once (moving across board columns) and only that task goes back if the server refuses;
 * other cards moved meanwhile keep their place. Lists refresh once the last pending change settles: an earlier
 * refetch would put cards still on their way back in their old column.
 */
export function useUpdateTaskStatus() {
  const queryClient = useQueryClient()
  const refresh = useRefreshTasks()
  const timeChanged = useTimeDataChanged()
  return useMutation({
    mutationKey: taskStatusMutationKey,
    mutationFn: ({ code, statusId }: StatusChange) => updateTaskStatus(code, statusId),
    meta: { successMessage: 'Estado actualizado.' },
    onMutate: async ({ code, statusId }) => {
      const status = queryClient.getQueryData<TaskStatus[]>(taskStatusesKey)?.find((candidate) => candidate.id === statusId)
      if (!status) return { moved: false as const }
      await queryClient.cancelQueries(cachesOf(code))
      return { moved: true as const, previous: writeStatus(queryClient, code, status) }
    },
    // The error itself toasts (mutation cache); here the card goes back to its column.
    onError: (_error, { code }, context) => {
      if (context?.moved) writeStatus(queryClient, code, context.previous)
    },
    onSettled: (task) => {
      // This mutation still counts as pending here.
      if (queryClient.isMutating({ mutationKey: taskStatusMutationKey }) > 1) {
        if (task) queryClient.setQueryData<Task>(taskKey(task.code), (current) => ({ ...current, ...task }))
        return
      }
      return Promise.all([refresh({ task: task ?? undefined }), timeChanged()])
    },
  })
}

/** Codes (lowercase) of the tasks whose status is being saved: only their cards wait. */
export function useSavingStatusCodes(): string[] {
  return useMutationState({
    filters: { mutationKey: taskStatusMutationKey, status: 'pending' },
    select: (mutation) => (mutation.state.variables as StatusChange).code.toLowerCase(),
  })
}
