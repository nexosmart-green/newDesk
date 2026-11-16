import { useQuery } from '@tanstack/react-query'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { timerKey } from '@/lib/queryKeys'
import { fetchTimer } from './timerApi'
import { isProvisional } from './timerOptimistic'
import type { TimerState } from './types'
import { useElapsedSeconds } from './useElapsedSeconds'

/** Only what ▶/■ needs: the poll every 30 s changes the elapsed time, which must not re-render every row. */
const runningOf = ({ active }: TimerState) =>
  active ? { id: active.id, code: active.task.code, provisional: isProvisional(active) } : null

/**
 * Reads the timer the header polls (no poll of its own: one per row would multiply the requests), through
 * `select`: structural sharing keeps the result identical between polls, so the row doesn't re-render.
 */
export function useRunningTimer() {
  const enabled = useHasModule(MODULES.time)
  return useQuery({ enabled, queryKey: timerKey, queryFn: fetchTimer, select: runningOf }).data ?? null
}

/**
 * The running entry's seconds, ticking, read from the header's cache. `staleTime: Infinity`: mounting
 * this (e.g. the row that just started) must not refetch, or the reply would wipe the optimistic start.
 */
export function useRunningElapsedSeconds(): number | null {
  const enabled = useHasModule(MODULES.time)
  const timer = useQuery({
    enabled,
    queryKey: timerKey,
    queryFn: fetchTimer,
    staleTime: Infinity,
    select: ({ active }: TimerState) => active?.elapsed_seconds ?? null,
  })
  return useElapsedSeconds(timer.data ?? null, timer.dataUpdatedAt)
}
