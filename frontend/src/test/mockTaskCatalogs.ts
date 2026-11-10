import * as taskCatalogsApi from '@/features/taskCatalogs/taskCatalogsApi'
import type { TaskCategory, TaskLabel } from '@/features/tasks/types'

export const taskCategories: TaskCategory[] = [
  { id: 1, name: 'Backend-API', tasks_count: 3 },
  { id: 2, name: 'Diseño', tasks_count: 0 },
]

export const taskLabels: TaskLabel[] = [
  { id: 7, name: 'Cliente urgente', color: '#f27474' },
  { id: 8, name: 'Deuda técnica', color: '#4f8cff' },
]

/** Any screen with the task form or the task filters reads both catalogs. */
export function mockTaskCatalogs(categories = taskCategories, labels = taskLabels) {
  return {
    categories: vi.spyOn(taskCatalogsApi, 'fetchTaskCategories').mockResolvedValue(categories),
    labels: vi.spyOn(taskCatalogsApi, 'fetchTaskLabels').mockResolvedValue(labels),
  }
}
