import type { TaskCategory, TaskLabel } from '@/features/tasks/types'
import { api, dataOf, envelopeOf, type ApiEnvelope } from '@/lib/api'

export interface LabelInput {
  name: string
  color: string
}

export async function fetchTaskCategories(): Promise<TaskCategory[]> {
  return dataOf(api.get<ApiEnvelope<TaskCategory[]>>('/task-categories'))
}

export async function createTaskCategory(name: string): Promise<TaskCategory> {
  return dataOf(api.post<ApiEnvelope<TaskCategory>>('/task-categories', { name }))
}

export async function renameTaskCategory(id: number, name: string): Promise<TaskCategory> {
  return dataOf(api.put<ApiEnvelope<TaskCategory>>(`/task-categories/${id}`, { name }))
}

export async function deleteTaskCategory(id: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/task-categories/${id}`))).message
}

export async function fetchTaskLabels(): Promise<TaskLabel[]> {
  return dataOf(api.get<ApiEnvelope<TaskLabel[]>>('/task-labels'))
}

export async function createTaskLabel(input: LabelInput): Promise<TaskLabel> {
  return dataOf(api.post<ApiEnvelope<TaskLabel>>('/task-labels', input))
}

export async function updateTaskLabel(id: number, input: LabelInput): Promise<TaskLabel> {
  return dataOf(api.put<ApiEnvelope<TaskLabel>>(`/task-labels/${id}`, input))
}

export async function deleteTaskLabel(id: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/task-labels/${id}`))).message
}
