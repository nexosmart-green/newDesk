/** Desk's teal first; the rest are distinguishable from each other in both themes. */
export const LABEL_COLORS = ['#309798', '#4f8cff', '#8b5cf6', '#e05d9c', '#f27474', '#f5b942', '#3ecf8e', '#99a5b5'] as const

export const DEFAULT_LABEL_COLOR = LABEL_COLORS[0]

export function tasksCountText(count: number | undefined): string {
  if (!count) return 'Sin tareas'
  return count === 1 ? '1 tarea' : `${count} tareas`
}
