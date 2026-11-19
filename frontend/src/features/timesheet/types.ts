import type { EntrySource } from '@/features/timer/types'

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
