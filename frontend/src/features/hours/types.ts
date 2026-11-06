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

export type BagLevel = 'ok' | 'warning' | 'exhausted'

/** RF-03.05: green < 80% ≤ yellow < 100% ≤ red. */
export function bagLevelOf(percent: number | null): BagLevel {
  if (percent === null || percent < 80) return 'ok'
  return percent < 100 ? 'warning' : 'exhausted'
}
