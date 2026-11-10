/** RF-05.16: the backend refuses shorter comments; the UI mirrors it so the button never lies. */
export const MIN_COMMENT_LENGTH = 10

export function isValidComment(comment: string): boolean {
  return comment.trim().length >= MIN_COMMENT_LENGTH
}

export type EntrySource = 'timer' | 'manual' | 'migration' | 'day_clock'

export const entrySourceLabels: Record<EntrySource, string> = {
  timer: 'Cronómetro',
  manual: 'Manual',
  migration: 'Migración',
  day_clock: 'Reloj del día',
}

/** A worked stretch or the running timer (`TimeEntryResource`). Times are UTC ISO-8601. */
export interface TimeEntry {
  id: number
  is_running: boolean
  started_at: string
  ended_at: string | null
  /** Computed by the server at `server_time` (RF-05.03): never derive it from the browser clock. */
  elapsed_seconds: number
  server_time: string
  source: EntrySource
  channel: string
  comment: string | null
  comment_pending: boolean
  stop_reason: string | null
  stop_reason_label: string | null
  task: { id: number; code: string; title: string }
  project: { id: number; name: string }
  user: { id: number; full_name: string }
}

/**
 * RF-05.19: one clock per person per day, not tied to a task. `pending` = stopped but not split yet;
 * `elapsed_seconds` is now−started while running, ended−started once pending. A forgotten clock is cut
 * by the server at local midnight (`cutoff_at`).
 */
export interface DayClock {
  id: number
  started_at: string
  ended_at: string | null
  cutoff_at: string
  status: 'running' | 'pending'
  elapsed_seconds: number
  server_time: string
}

export interface TimerState {
  active: TimeEntry | null
  pending_comments: TimeEntry[]
  day_clock: DayClock | null
  server_time: string
}

/** One part of a split day: `from`/`to` are integer seconds from the day's `started_at`. */
export interface DayPartInput {
  from: number
  to: number
  project_id: number
  task_id: number | null
  /** Instead of `task_id`: a task created on save, assigned to me. */
  new_task_title: string | null
  comment: string
}

export interface DaySplitResult {
  entries: TimeEntry[]
  /** Projects whose bag this split pushed below zero (saved anyway; admin and leader are told). */
  overdrawn: { project_id: number; name: string; seconds: number }[]
}

/** Stable `data.code` values the timer endpoints answer with (TimerRules + actions). */
export const timerRule = {
  noHoursAvailable: 'no_hours_available',
  previousCommentRequired: 'previous_comment_required',
  commentRequired: 'comment_required',
  commentPending: 'comment_pending',
  taskNotAssigned: 'task_not_assigned',
  projectClosed: 'project_closed',
  taskClosed: 'task_closed',
  manualExceedsBag: 'manual_exceeds_bag',
  outsideManualWindow: 'outside_manual_window',
  timerBusy: 'timer_busy',
  dayClockRunning: 'day_clock_running',
  daySplitPending: 'day_split_pending',
} as const

export interface ManualEntryInput {
  /** `YYYY-MM-DD` and `HH:mm` in the person's timezone; the backend converts to UTC. */
  date: string
  from: string
  to: string
  comment: string
}

export interface CorrectEntryInput {
  date?: string
  from?: string
  to?: string
  comment: string
}
