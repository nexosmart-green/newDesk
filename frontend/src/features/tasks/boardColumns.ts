import type { TaskFilters, TaskStatus } from './types'

/** Columns follow the filter: a picked status, every status with closed ones, or the open ones. */
export function boardColumns(statuses: TaskStatus[], filters: TaskFilters): TaskStatus[] {
  const sorted = [...statuses].sort((left, right) => left.position - right.position)
  if (filters.status_ids.length > 0) return sorted.filter((status) => filters.status_ids.includes(status.id))
  return filters.include_closed ? sorted : sorted.filter((status) => status.kind !== 'closed')
}
