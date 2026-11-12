import { useMutation, useQuery, useQueryClient, type Query } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { taskCategoriesKey, taskLabelsKey, taskListsKeys, tasksKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
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

export function useTaskCategories() {
  return useQuery({ queryKey: taskCategoriesKey, queryFn: fetchTaskCategories, staleTime: 5 * 60_000 })
}

export function useTaskLabels() {
  return useQuery({ queryKey: taskLabelsKey, queryFn: fetchTaskLabels, staleTime: 5 * 60_000 })
}

/** Every task detail (not its tabs). */
const taskDetails = { queryKey: [...tasksKey, 'detail'], predicate: (query: Query) => query.queryKey.length === 3 }

/** The settings panel waits for its list; task rows and details embed the name and color, so renames and deletes refresh them behind. */
function useRefresh(key: readonly string[]) {
  const queryClient = useQueryClient()
  return (withTasks: boolean) => refreshQueries(queryClient, { now: [key], later: withTasks ? [...taskListsKeys, taskDetails] : [] })
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
    onSuccess: (_category, { name }) => {
      toast.success(`Categoría «${name}» guardada.`)
      return refresh(true)
    },
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
    onSuccess: (_label, { input }) => {
      toast.success(`Etiqueta «${input.name}» guardada.`)
      return refresh(true)
    },
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
