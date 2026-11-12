import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { toastTextOf } from '@/lib/errorText'
import { checklistMutationKey, taskActivityKey, taskKey, taskListsKeys } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { ChecklistItem, Task } from '../types'
import { addChecklistItem, deleteChecklistItem, reorderChecklist, updateChecklistItem } from './checklistApi'

export function progressOf(items: ChecklistItem[]) {
  return { done: items.filter((item) => item.done).length, total: items.length }
}

/**
 * The checklist lives inside the task detail (no query of its own): mutations patch that cache, then
 * refresh, in the background, the task rows (badges) and its Historial once the last pending change settles.
 */
function useChecklistCache(code: string) {
  const queryClient = useQueryClient()
  const key = taskKey(code)
  const mutationKey = checklistMutationKey(code)

  const read = () => queryClient.getQueryData<Task>(key)?.checklist ?? []
  const write = (update: (items: ChecklistItem[]) => ChecklistItem[]) =>
    queryClient.setQueryData<Task>(key, (task) => {
      if (!task) return task
      const items = update(task.checklist ?? [])
      return { ...task, checklist: items, checklist_progress: progressOf(items) }
    })

  /** Snapshot for rollback, with in-flight refetches cancelled so they can't overwrite the optimistic state. */
  async function snapshot() {
    await queryClient.cancelQueries({ queryKey: key, exact: true })
    return read()
  }

  function settle() {
    // This mutation still counts as pending here: refresh only when it is the last one.
    if (queryClient.isMutating({ mutationKey }) > 1) return
    void refreshQueries(queryClient, { later: [...taskListsKeys, taskActivityKey(code)] })
  }

  const replaceItem = (saved: ChecklistItem) => write((items) => items.map((item) => (item.id === saved.id ? saved : item)))

  return { mutationKey, write, snapshot, settle, replaceItem }
}

function rollbackWith(write: ReturnType<typeof useChecklistCache>['write'], title: string) {
  return (error: unknown, _variables: unknown, previous: ChecklistItem[] | undefined) => {
    if (previous) write(() => previous)
    toast.error(toastTextOf(error).message, { title })
  }
}

/** Optimistic: the box ticks at once and goes back (with the reason) if the server refuses. */
export function useToggleChecklistItem(code: string) {
  const cache = useChecklistCache(code)
  return useMutation({
    mutationKey: cache.mutationKey,
    meta: { silentError: true },
    mutationFn: ({ id, done }: { id: number; done: boolean }) => updateChecklistItem(id, { done }),
    onMutate: async ({ id, done }) => {
      await cache.snapshot()
      cache.write((items) => items.map((item) => (item.id === id ? { ...item, done } : item)))
    },
    // Undo only this box: restoring a whole snapshot would also revert other toggles still in flight.
    onError: (error, { id, done }) => {
      cache.write((items) => items.map((item) => (item.id === id ? { ...item, done: !done } : item)))
      toast.error(toastTextOf(error).message, { title: 'No se pudo actualizar la subtarea' })
    },
    onSuccess: cache.replaceItem,
    onSettled: cache.settle,
  })
}

export function useAddChecklistItem(code: string) {
  const cache = useChecklistCache(code)
  return useMutation({
    mutationKey: cache.mutationKey,
    mutationFn: (text: string) => addChecklistItem(code, text),
    onSuccess: (item) => cache.write((items) => [...items, item]),
    onSettled: cache.settle,
  })
}

/** Field errors show under the inline input; business rules toast. */
export function useRenameChecklistItem(code: string) {
  const cache = useChecklistCache(code)
  return useMutation({
    mutationKey: cache.mutationKey,
    mutationFn: ({ id, text }: { id: number; text: string }) => updateChecklistItem(id, { text }),
    onSuccess: cache.replaceItem,
    onSettled: cache.settle,
  })
}

export function useDeleteChecklistItem(code: string) {
  const cache = useChecklistCache(code)
  return useMutation({
    mutationKey: cache.mutationKey,
    meta: { successMessage: 'Subtarea borrada.' },
    mutationFn: (id: number) => deleteChecklistItem(id),
    onSuccess: (_result, id) => cache.write((items) => items.filter((item) => item.id !== id)),
    onSettled: cache.settle,
  })
}

/** Optimistic like the toggle: the row moves at once. */
export function useReorderChecklist(code: string) {
  const cache = useChecklistCache(code)
  return useMutation({
    mutationKey: cache.mutationKey,
    meta: { silentError: true },
    mutationFn: (ids: number[]) => reorderChecklist(code, ids),
    onMutate: async (ids) => {
      const previous = await cache.snapshot()
      const byId = new Map(previous.map((item) => [item.id, item]))
      cache.write(() => ids.flatMap((id) => byId.get(id) ?? []))
      return previous
    },
    onError: rollbackWith(cache.write, 'No se pudo reordenar'),
    onSuccess: (items) => cache.write(() => items),
    onSettled: cache.settle,
  })
}
