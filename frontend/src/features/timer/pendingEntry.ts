import type { TimeEntry } from './types'

/** What the pending-comment dialog shows about an entry, whatever endpoint it came from. */
export interface PendingEntrySummary {
  id: number
  taskCode: string
  taskTitle: string
  project: string
  startedAt: string
  seconds: number
  stopReasonLabel?: string | null
}

export function toPendingSummary(entry: TimeEntry): PendingEntrySummary {
  return {
    id: entry.id,
    taskCode: entry.task.code,
    taskTitle: entry.task.title,
    project: entry.project.name,
    startedAt: entry.started_at,
    seconds: entry.elapsed_seconds,
    stopReasonLabel: entry.stop_reason_label,
  }
}
