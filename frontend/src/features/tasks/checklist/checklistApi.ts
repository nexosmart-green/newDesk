import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { ChecklistItem } from '../types'

const checklistPath = (code: string) => `/tasks/${encodeURIComponent(code)}/checklist`
const itemPath = (id: number) => `/checklist-items/${id}`

/** RF-04.08: added at the end. The backend caps a task at 100 items (422 with the reason). */
export async function addChecklistItem(code: string, text: string): Promise<ChecklistItem> {
  return dataOf(api.post<ApiEnvelope<ChecklistItem>>(checklistPath(code), { text }))
}

/** `done: true` records who and when. */
export async function updateChecklistItem(id: number, changes: { text?: string; done?: boolean }): Promise<ChecklistItem> {
  return dataOf(api.put<ApiEnvelope<ChecklistItem>>(itemPath(id), changes))
}

export async function deleteChecklistItem(id: number): Promise<void> {
  await dataOf(api.delete<ApiEnvelope<unknown>>(itemPath(id)))
}

/** `ids` must be exactly the task's items, in the new order. */
export async function reorderChecklist(code: string, ids: number[]): Promise<ChecklistItem[]> {
  return dataOf(api.put<ApiEnvelope<ChecklistItem[]>>(`${checklistPath(code)}/order`, { ids }))
}
