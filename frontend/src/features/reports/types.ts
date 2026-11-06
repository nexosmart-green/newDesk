import type { HourBag } from '@/features/hours/types'

export const REPORTS_PERMISSION = 'projects.manage'

export interface ReportProjectRef {
  id: number
  name: string
  prefix: string
}

export interface ReportPeriod {
  from: string
  to: string
}

/** Both optional: the backend defaults to the current month (up to today) in the viewer's timezone. */
export type ReportPeriodQuery = Partial<ReportPeriod>

interface HoursFigures {
  /** null when the project has no hour cap. */
  allocated_seconds: number | null
  consumed_total_seconds: number
  consumed_in_period_seconds: number
  percent_consumed: number | null
}

/** RF-10.01 one row of `GET /reports/hours`. */
export interface HoursReportRow extends HoursFigures {
  project: ReportProjectRef
  client: string | null
  type: string
}

export interface HoursReport {
  period: ReportPeriod
  projects: HoursReportRow[]
}

export interface ProjectHoursReport extends HoursFigures {
  project: ReportProjectRef
  period: ReportPeriod
  by_person: { user_id: number; name: string; seconds: number }[]
  by_task: { code: string; title: string; seconds: number }[]
}

/** Adapts report figures to the bag the shared `HoursBar` renders (same colors as the project screens). */
export function bagOf(figures: HoursFigures): HourBag {
  const hasCap = figures.allocated_seconds !== null
  return {
    has_cap: hasCap,
    allocated_seconds: figures.allocated_seconds,
    consumed_seconds: figures.consumed_total_seconds,
    running_seconds: 0,
    available_seconds: hasCap ? (figures.allocated_seconds ?? 0) - figures.consumed_total_seconds : null,
    percent_consumed: figures.percent_consumed,
    is_exhausted: (figures.percent_consumed ?? 0) >= 100,
  }
}
