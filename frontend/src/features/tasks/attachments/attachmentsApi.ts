import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { TaskAttachment } from '../types'
import { percentReporter } from '../uploads/percentReporter'

const attachmentsPath = (code: string) => `/tasks/${encodeURIComponent(code)}/attachments`

/** RF-04.09: newest first. */
export async function fetchTaskAttachments(code: string): Promise<TaskAttachment[]> {
  return dataOf(api.get<ApiEnvelope<TaskAttachment[]>>(attachmentsPath(code)))
}

export async function uploadTaskAttachment(code: string, file: File, onProgress: (percent: number) => void): Promise<TaskAttachment> {
  const body = new FormData()
  body.append('file', file)
  return dataOf(api.post<ApiEnvelope<TaskAttachment>>(attachmentsPath(code), body, { onUploadProgress: percentReporter(onProgress) }))
}

/** Whoever uploaded it, or `projects.manage`; the file leaves the disk too. */
export async function deleteTaskAttachment(uuid: string): Promise<void> {
  await dataOf(api.delete<ApiEnvelope<unknown>>(`/task-attachments/${encodeURIComponent(uuid)}`))
}
