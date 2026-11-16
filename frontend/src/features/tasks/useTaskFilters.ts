import { useState } from 'react'
import { useCurrentUser } from '@/features/auth/useAuth'
import { readStoredJson, writeStoredJson } from '@/lib/storage'
import { defaultTaskFilters, type TaskFilters } from './types'

/** Where the filters are used: the project "Tareas" tab, "Mis tareas" or "Todas las tareas". Each remembers its own. */
type TaskFilterScope = 'project' | 'mine' | 'all'

export function taskFiltersStorageKey(scope: TaskFilterScope, userId: number): string {
  return `newdesk:task-filters:${scope}:${userId}`
}

const idOf = (value: unknown, fallback: number | null) => (Number.isInteger(value) ? (value as number) : fallback)

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** Stored JSON is untrusted: keep each field only when it has the right type, defaults otherwise. */
function parseStoredTaskFilters(stored: unknown): TaskFilters {
  if (!isRecord(stored)) return { ...defaultTaskFilters }
  const statusIds = stored.status_ids
  return {
    status_ids:
      Array.isArray(statusIds) && statusIds.every((id) => Number.isInteger(id))
        ? (statusIds as number[])
        : defaultTaskFilters.status_ids,
    include_closed: typeof stored.include_closed === 'boolean' ? stored.include_closed : defaultTaskFilters.include_closed,
    assignee_id: idOf(stored.assignee_id, defaultTaskFilters.assignee_id),
    overdue: typeof stored.overdue === 'boolean' ? stored.overdue : defaultTaskFilters.overdue,
    search: typeof stored.search === 'string' ? stored.search : defaultTaskFilters.search,
    category_id: idOf(stored.category_id, defaultTaskFilters.category_id),
    label_id: idOf(stored.label_id, defaultTaskFilters.label_id),
    project_id: idOf(stored.project_id, defaultTaskFilters.project_id),
  }
}

function load(scope: TaskFilterScope, userId: number | undefined): TaskFilters {
  return userId === undefined ? { ...defaultTaskFilters } : parseStoredTaskFilters(readStoredJson(taskFiltersStorageKey(scope, userId)))
}

/**
 * RF-04.05: filters persist per user (this browser). `preset` (e.g. the Panel's "Vencidas" link)
 * applies on top of the stored ones when the page opens, without being saved until the person changes something.
 */
export function useTaskFilters(scope: TaskFilterScope, preset?: Partial<TaskFilters>) {
  const userId = useCurrentUser().data?.id
  const [state, setState] = useState(() => ({ userId, filters: { ...load(scope, userId), ...preset } }))

  // The user can arrive after the first render (or change on logout/login): reload their filters.
  if (state.userId !== userId) setState({ userId, filters: { ...load(scope, userId), ...preset } })

  function change(changes: Partial<TaskFilters>) {
    setState((current) => {
      const filters = { ...current.filters, ...changes }
      if (current.userId !== undefined) writeStoredJson(taskFiltersStorageKey(scope, current.userId), filters)
      return { ...current, filters }
    })
  }

  return { filters: state.filters, change }
}
