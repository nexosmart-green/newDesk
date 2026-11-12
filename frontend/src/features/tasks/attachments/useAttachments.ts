import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef } from 'react'
import { toast } from '@/components/ui/toast/toastStore'
import type { TaskAttachment } from '../types'
import { useUploadQueue } from '../uploads/useUploadQueue'
import { dashboardKey, taskKey, tasksKey } from '../useTasks'
import { attachmentProblem, MAX_ATTACHMENTS_PER_TASK, TOO_MANY_ATTACHMENTS } from './attachmentRules'
import { deleteTaskAttachment, fetchTaskAttachments, uploadTaskAttachment } from './attachmentsApi'

const attachmentsKey = (code: string) => [...taskKey(code), 'attachments'] as const

/** Under the task's key: the detail's counter, the rows' clip and the Historial refresh together. */
function useRefreshAttachments() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([queryClient.invalidateQueries({ queryKey: tasksKey }), queryClient.invalidateQueries({ queryKey: dashboardKey })])
}

export function useTaskAttachments(code: string) {
  return useQuery({ queryKey: attachmentsKey(code), queryFn: () => fetchTaskAttachments(code) })
}

export function useDeleteAttachment(code: string) {
  const queryClient = useQueryClient()
  const refresh = useRefreshAttachments()
  return useMutation({
    mutationFn: (attachment: TaskAttachment) => deleteTaskAttachment(attachment.uuid),
    onSuccess: (_result, attachment) => {
      toast.success(`«${attachment.name}» borrado.`)
      queryClient.setQueryData<TaskAttachment[]>(attachmentsKey(code), (current) => current?.filter((item) => item.uuid !== attachment.uuid))
      return refresh()
    },
  })
}

/** Several files at once, each with its bar; refused ones stay listed with the reason. */
export function useAttachmentUploads(code: string, existing: number) {
  const queryClient = useQueryClient()
  const refresh = useRefreshAttachments()
  // Room left for this batch, spent as each file passes the other checks.
  const slots = useRef(0)
  const queue = useUploadQueue<TaskAttachment>({
    validate: (file) => attachmentProblem(file) ?? (slots.current-- > 0 ? null : TOO_MANY_ATTACHMENTS),
    send: (file, onProgress) => uploadTaskAttachment(code, file, onProgress),
    failureTitle: 'No se pudo adjuntar',
    keepFailures: true,
    onUploaded: (attachment) => {
      toast.success(`«${attachment.name}» adjuntado.`)
      queryClient.setQueryData<TaskAttachment[]>(attachmentsKey(code), (current) => [attachment, ...(current ?? [])])
      void refresh()
    },
  })

  function add(files: File[]) {
    slots.current = MAX_ATTACHMENTS_PER_TASK - existing - queue.inFlight
    for (const file of files) queue.start(file)
  }

  return { uploads: queue.items, add, retry: queue.retry, dismiss: queue.dismiss }
}
