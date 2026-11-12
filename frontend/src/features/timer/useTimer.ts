import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { toast } from '@/components/ui/toast/toastStore'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { formatDuration } from '@/lib/duration'
import { toastTextOf } from '@/lib/errorText'
import { dashboardKey, hoursKey, projectsKey, taskKey, tasksKey, timesheetKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import {
  completeEntryComment,
  correctTimeEntry,
  deleteTimeEntry,
  fetchTimer,
  logManualTime,
  startTimer,
  stopTimer,
} from './timerApi'
import { confirmStarted, optimisticStart, optimisticStop, rollback, timerKey, type StartingTask } from './timerOptimistic'
import { broadcastTimerChanged, subscribeTimerChanged } from './timerSync'
import type { CorrectEntryInput, ManualEntryInput } from './types'

export { timerKey }
/**
 * Everything a time entry can move: the header, project bags, the hours tab, Mi semana and its
 * history, the tasks' logged hours (lists + detail "Horas") and the Panel's week.
 */
const timeDependentKeys = [timerKey, hoursKey, timesheetKey, projectsKey, tasksKey, dashboardKey] as const

export const TIMER_POLL_MS = 30_000

/** "0h 00m" reads like nothing was saved; under a minute says so. */
const recordedText = (seconds: number) => (seconds < 60 ? 'menos de un minuto' : formatDuration(seconds))

/** RF-05.03 / RNF-12: the server owns the timer; refetch on focus and every 30 s. Off without the time module. */
export function useTimerState() {
  const enabled = useHasModule(MODULES.time)
  return useQuery({
    enabled,
    queryKey: timerKey,
    queryFn: fetchTimer,
    staleTime: 0,
    refetchOnWindowFocus: 'always',
    refetchInterval: TIMER_POLL_MS,
  })
}

/** Mount once (header): other tabs' starts/stops refresh this one instantly. */
export function useTimerTabSync() {
  const queryClient = useQueryClient()
  useEffect(
    () => subscribeTimerChanged(() => void queryClient.invalidateQueries({ queryKey: timerKey })),
    [queryClient],
  )
}

/**
 * After any time mutation: tell the other tabs and refresh dependent data in the background. Resolves once
 * `now` (what the current screen shows) is fresh; nothing else waits.
 */
export function useTimeDataChanged() {
  const queryClient = useQueryClient()
  return (now: readonly (readonly unknown[])[] = []) => {
    broadcastTimerChanged()
    return refreshQueries(queryClient, { now, later: timeDependentKeys })
  }
}

/**
 * Optimistic: the header flips to the new task at once and rolls back if the server says no.
 * The caller explains refusals (comment needed, no hours…), hence `silentError`.
 */
export function useStartTimer() {
  const queryClient = useQueryClient()
  const changed = useTimeDataChanged()
  return useMutation({
    meta: { silentError: true },
    mutationFn: ({ task, previousComment }: { task: StartingTask; previousComment?: string }) =>
      startTimer(task.code, previousComment),
    onMutate: ({ task }) => optimisticStart(queryClient, task),
    onError: (_error, _variables, snapshot) => rollback(queryClient, snapshot),
    onSuccess: (entry) => {
      confirmStarted(queryClient, entry)
      return changed()
    },
  })
}

/** Optimistic too: the stop dialog closes on send; a refusal brings the timer back with a toast. */
export function useStopTimer() {
  const queryClient = useQueryClient()
  const changed = useTimeDataChanged()
  return useMutation({
    meta: { silentError: true },
    mutationFn: (comment: string) => stopTimer(comment),
    onMutate: () => optimisticStop(queryClient),
    onError: (error, _comment, snapshot) => {
      rollback(queryClient, snapshot)
      const text = toastTextOf(error)
      toast.error(text.message, { title: 'El cronómetro sigue corriendo' })
    },
    onSuccess: (entry) => {
      toast.success(`Registraste ${recordedText(entry.elapsed_seconds)} en ${entry.task.code}.`, { title: 'Cronómetro detenido' })
      return changed()
    },
  })
}

export function useCompleteEntryComment() {
  const changed = useTimeDataChanged()
  return useMutation({
    // The comment dialog shows refusals inline (it stays open to fix the text).
    meta: { successMessage: 'Comentario guardado.', silentError: true },
    mutationFn: ({ entryId, comment }: { entryId: number; comment: string }) => completeEntryComment(entryId, comment),
    // The pending-comments banner reads the timer state.
    onSuccess: () => changed([timerKey]),
  })
}

export function useLogManualTime(taskCode: string) {
  const changed = useTimeDataChanged()
  return useMutation({
    // The form explains rule refusals itself (bag empty, outside the window) with the way out.
    meta: { successMessage: 'Horas cargadas.', silentError: true },
    mutationFn: (input: ManualEntryInput) => logManualTime(taskCode, input),
    // The form sits on the task's "Horas" tab: its list and total.
    onSuccess: () => changed([taskKey(taskCode)]),
  })
}

export function useCorrectTimeEntry() {
  const changed = useTimeDataChanged()
  return useMutation({
    meta: { successMessage: 'Registro corregido.' },
    mutationFn: ({ entryId, input }: { entryId: number; input: CorrectEntryInput }) => correctTimeEntry(entryId, input),
    // Corrected and deleted from the project's hours tab.
    onSuccess: () => changed([hoursKey]),
  })
}

export function useDeleteTimeEntry() {
  const changed = useTimeDataChanged()
  return useMutation({
    mutationFn: (entryId: number) => deleteTimeEntry(entryId),
    onSuccess: (message) => {
      toast.success(message ?? 'Registro borrado.')
      return changed([hoursKey])
    },
  })
}
