import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import {
  completeEntryComment,
  correctTimeEntry,
  deleteTimeEntry,
  fetchTimer,
  logManualTime,
  startTimer,
  stopTimer,
} from './timerApi'
import { broadcastTimerChanged, subscribeTimerChanged } from './timerSync'
import type { CorrectEntryInput, ManualEntryInput } from './types'

export const timerKey = ['timer'] as const
/** Everything a time entry can move: the header, project bags, the hours tab, Mi semana. */
const timeDependentKeys = [timerKey, ['hours'], ['timesheet'], ['projects']] as const

export const TIMER_POLL_MS = 30_000

/** RF-05.03 / RNF-12: the server owns the timer; refetch on focus and every 30 s. */
export function useTimerState() {
  return useQuery({
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

/** After any time mutation: refresh dependent data here and tell the other tabs. */
export function useTimeDataChanged() {
  const queryClient = useQueryClient()
  return async () => {
    broadcastTimerChanged()
    await Promise.all(timeDependentKeys.map((queryKey) => queryClient.invalidateQueries({ queryKey })))
  }
}

export function useStartTimer() {
  const changed = useTimeDataChanged()
  return useMutation({
    mutationFn: ({ taskCode, previousComment }: { taskCode: string; previousComment?: string }) =>
      startTimer(taskCode, previousComment),
    onSuccess: changed,
  })
}

export function useStopTimer() {
  const changed = useTimeDataChanged()
  return useMutation({ mutationFn: (comment: string) => stopTimer(comment), onSuccess: changed })
}

export function useCompleteEntryComment() {
  const changed = useTimeDataChanged()
  return useMutation({
    mutationFn: ({ entryId, comment }: { entryId: number; comment: string }) => completeEntryComment(entryId, comment),
    onSuccess: changed,
  })
}

export function useLogManualTime(taskCode: string) {
  const changed = useTimeDataChanged()
  return useMutation({ mutationFn: (input: ManualEntryInput) => logManualTime(taskCode, input), onSuccess: changed })
}

export function useCorrectTimeEntry() {
  const changed = useTimeDataChanged()
  return useMutation({
    mutationFn: ({ entryId, input }: { entryId: number; input: CorrectEntryInput }) => correctTimeEntry(entryId, input),
    onSuccess: changed,
  })
}

export function useDeleteTimeEntry() {
  const changed = useTimeDataChanged()
  return useMutation({ mutationFn: (entryId: number) => deleteTimeEntry(entryId), onSuccess: changed })
}
