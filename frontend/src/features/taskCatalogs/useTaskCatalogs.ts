import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { tasksKey } from '@/features/tasks/useTasks'
import {
  createTaskCategory,
  createTaskLabel,
  deleteTaskCategory,
  deleteTaskLabel,
  fetchTaskCategories,
  fetchTaskLabels,
  renameTaskCategory,
  updateTaskLabel,
  type LabelInput,
} from './taskCatalogsApi'

export const taskCategoriesKey = ['task-categories'] as const
export const taskLabelsKey = ['task-labels'] as const

export function useTaskCategories() {
  return useQuery({ queryKey: taskCategoriesKey, queryFn: fetchTaskCategories, staleTime: 5 * 60_000 })
}

export function useTaskLabels() {
  return useQuery({ queryKey: taskLabelsKey, queryFn: fetchTaskLabels, staleTime: 5 * 60_000 })
}

/** Task rows embed the category/label name and color, so renames and deletes refresh them too. */
function useRefresh(key: readonly string[]) {
  const queryClient = useQueryClient()
  return (withTasks: boolean) =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: key }),
      withTasks ? queryClient.invalidateQueries({ queryKey: tasksKey }) : null,
    ])
}

export function useCreateTaskCategory() {
  const refresh = useRefresh(taskCategoriesKey)
  return useMutation({
    mutationFn: (name: string) => createTaskCategory(name),
    onSuccess: (category) => {
      toast.success(`Categoría «${category.name}» creada.`)
      return refresh(false)
    },
  })
}

export function useRenameTaskCategory() {
  const refresh = useRefresh(taskCategoriesKey)
  return useMutation({
    mutationFn: ({ id, name }: { id: number; name: string }) => renameTaskCategory(id, name),
    meta: { successMessage: 'Categoría actualizada.' },
    onSuccess: () => refresh(true),
  })
}

export function useDeleteTaskCategory() {
  const refresh = useRefresh(taskCategoriesKey)
  return useMutation({
    mutationFn: (id: number) => deleteTaskCategory(id),
    onSuccess: (message) => {
      toast.success(message ?? 'Categoría borrada.')
      return refresh(true)
    },
  })
}

export function useCreateTaskLabel() {
  const refresh = useRefresh(taskLabelsKey)
  return useMutation({
    mutationFn: (input: LabelInput) => createTaskLabel(input),
    onSuccess: (label) => {
      toast.success(`Etiqueta «${label.name}» creada.`)
      return refresh(false)
    },
  })
}

export function useUpdateTaskLabel() {
  const refresh = useRefresh(taskLabelsKey)
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: LabelInput }) => updateTaskLabel(id, input),
    meta: { successMessage: 'Etiqueta actualizada.' },
    onSuccess: () => refresh(true),
  })
}

export function useDeleteTaskLabel() {
  const refresh = useRefresh(taskLabelsKey)
  return useMutation({
    mutationFn: (id: number) => deleteTaskLabel(id),
    onSuccess: (message) => {
      toast.success(message ?? 'Etiqueta borrada.')
      return refresh(true)
    },
  })
}
