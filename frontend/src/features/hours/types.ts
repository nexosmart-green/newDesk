import type { TimeEntry } from '@/features/timer/types'
import type { Paginated } from '@/lib/api'

/** Read model of a project's hour bag (RF-05 "Conceptos"), integer seconds (R-5). */
export interface HourBag {
  has_cap: boolean
  /** null for internal projects (RF-05.14: no cap). */
  allocated_seconds: number | null
  consumed_seconds: number
  running_seconds: number
  available_seconds: number | null
  percent_consumed: number | null
  is_exhausted: boolean
}

export interface HourAllocation {
  id: number
  seconds: number
  reason: string
  reference: string | null
  created_by: string
  created_at: string
}

export interface ProjectHours extends HourBag {
  allocations: HourAllocation[]
}

export interface AllocationInput {
  seconds: number
  reason: string
  reference: string | null
}

export interface ExtensionRequest {
  id: number
  status: 'pending' | 'approved' | 'rejected'
  message: string | null
  project: { id: number; name: string }
  requested_by: { id: number; full_name: string }
  created_at: string | null
}

export interface ProjectEntryFilters {
  user_id: number | null
  task_id: number | null
  from: string
  to: string
  page: number
}

export const emptyProjectEntryFilters: ProjectEntryFilters = { user_id: null, task_id: null, from: '', to: '', page: 1 }

/** "Mis horas": a range of local dates (both required, 366 days max, checked by the backend) plus optional filters. */
export interface MyEntryFilters {
  /** `YYYY-MM-DD`, inclusive, in the viewer's timezone. */
  from: string
  to: string
  project_id: number | null
  /** Comment, task code or title. */
  search: string
  /** null = me; another person needs `time.manage` (403 otherwise). */
  user_id: number | null
}

/** This month up to today: what "Mis horas" opens with. */
export function thisMonthEntryFilters(today: string): MyEntryFilters {
  return { from: `${today.slice(0, 8)}01`, to: today, project_id: null, search: '', user_id: null }
}

/** "Mis horas" calendar: same person/project filters over the month grid (62 days max). */
export type DailyHoursFilters = Pick<MyEntryFilters, 'user_id' | 'project_id' | 'from' | 'to'>

/** Seconds worked per date (in `timezone`), one item per date of the range; running timers included. */
export interface DailyHours {
  timezone: string
  total_seconds: number
  days: { date: string; total_seconds: number }[]
}

/** RF-05.15: entries started before `editable_since` are out of this viewer's window; null = no limit. */
export type EntryPage = Paginated<TimeEntry, { editable_since: string | null }>

/** "Mis horas" also brings the total of the whole filter, not just of the page; running timers count up to now. */
export type MyEntriesPage = Paginated<TimeEntry, { editable_since: string | null; total_seconds: number }>

export type BagLevel = 'ok' | 'warning' | 'exhausted'

/** RF-03.05: green < 80% ≤ yellow < 100% ≤ red. */
export function bagLevelOf(percent: number | null): BagLevel {
  if (percent === null || percent < 80) return 'ok'
  return percent < 100 ? 'warning' : 'exhausted'
}
