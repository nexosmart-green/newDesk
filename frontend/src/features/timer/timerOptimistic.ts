import type { QueryClient } from '@tanstack/react-query'
import type { TimeEntry, TimerState } from './types'

import { timerKey } from '@/lib/queryKeys'
export { timerKey }

/** What ▶ knows about the task before the server answers. */
export interface StartingTask {
  code: string
  title: string
  project?: { id: number; name?: string }
}

export interface TimerSnapshot {
  previous: TimerState | undefined
}

/** Placeholder ids are negative: the header knows the entry isn't confirmed yet. */
export const isProvisional = (entry: TimeEntry) => entry.id < 0

function provisionalEntry(task: StartingTask, now: string, user: TimeEntry['user']): TimeEntry {
  return {
    id: -Date.now(),
    is_running: true,
    started_at: now,
    ended_at: null,
    elapsed_seconds: 0,
    server_time: now,
    source: 'timer',
    channel: 'web',
    comment: null,
    comment_pending: false,
    stop_reason: null,
    stop_reason_label: null,
    task: { id: 0, code: task.code, title: task.title },
    project: { id: task.project?.id ?? 0, name: task.project?.name ?? '' },
    user,
  }
}

/** Stops any in-flight poll (it would overwrite us), remembers the state, applies `next`. */
async function apply(client: QueryClient, next: (current: TimerState) => TimerState): Promise<TimerSnapshot> {
  await client.cancelQueries({ queryKey: timerKey })
  const previous = client.getQueryData<TimerState>(timerKey)
  if (previous) client.setQueryData<TimerState>(timerKey, next(previous))
  return { previous }
}

/**
 * ▶ feels instant: the header shows the new task at 00:00:00 before the server confirms. Not while
 * comments are pending: the server will refuse (RF-05.17), so a flash of "running" would lie.
 */
export function optimisticStart(client: QueryClient, task: StartingTask): Promise<TimerSnapshot> {
  return apply(client, (current) => {
    if (current.pending_comments.length > 0) return current
    const now = new Date().toISOString()
    const user = current.active?.user ?? { id: 0, full_name: '' }
    return { ...current, active: provisionalEntry(task, now, user) }
  })
}

/** ■ feels instant: the header clears while the stop is saved. */
export function optimisticStop(client: QueryClient): Promise<TimerSnapshot> {
  return apply(client, (current) => ({ ...current, active: null }))
}

/** The server's entry replaces the placeholder right away (no flash back to the old state). */
export function confirmStarted(client: QueryClient, entry: TimeEntry): void {
  client.setQueryData<TimerState>(timerKey, (current) => (current ? { ...current, active: entry } : current))
}

export function rollback(client: QueryClient, snapshot: TimerSnapshot | undefined): void {
  if (snapshot?.previous) client.setQueryData<TimerState>(timerKey, snapshot.previous)
}
