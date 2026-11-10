import type { EntrySource } from '@/features/timer/types'
import type { TimedPage } from '@/lib/api'

export interface TimesheetEntry {
  id: number
  task: { code: string; title: string }
  project: string
  started_at: string
  ended_at: string | null
  seconds: number
  is_running: boolean
  comment: string | null
  comment_pending: boolean
  source: EntrySource
}

export interface TimesheetDay {
  /** `YYYY-MM-DD` in the person's timezone. */
  date: string
  total_seconds: number
  entries: TimesheetEntry[]
}

/** RF-05.12 "Mi semana". */
export interface Timesheet {
  timezone: string
  total_seconds: number
  days: TimesheetDay[]
}

/** "Historial": a free range (max 366 days, checked by the backend) plus optional filters. */
export interface HistoryFilters {
  /** `YYYY-MM-DD`, inclusive, in the person's timezone. */
  from: string
  to: string
  project_id: number | null
  search: string
  /** Someone else's entries: only with `time.manage` (403 otherwise). */
  user_id: number | null
}

export interface HistoryEntry {
  id: number
  user: { id: number; full_name: string }
  task: { code: string; title: string }
  project: { id: number; name: string }
  started_at: string
  ended_at: string | null
  seconds: number
  is_running: boolean
  comment: string | null
  comment_pending: boolean
  source: EntrySource
}

export type MyTimeEntriesPage = TimedPage<HistoryEntry>
