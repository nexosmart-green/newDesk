import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef } from 'react'
import { toast } from '@/components/ui/toast/toastStore'
import { taskActivityKey, taskAttachmentsKey, taskKey, taskListsKeys } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { Task, TaskAttachment } from '../types'
import { useUploadQueue } from '../uploads/useUploadQueue'
import { attachmentProblem, MAX_ATTACHMENTS_PER_TASK, TOO_MANY_ATTACHMENTS } from './attachmentRules'
import { deleteTaskAttachment, fetchTaskAttachments, uploadTaskAttachment } from './attachmentsApi'

/**
 * The list is patched in place and the tab's counter adjusted on the detail; the rows' clip and the
 * Historial refresh in the background (the comments and the rest of the task don't change).
 */
function useStoreAttachments(code: string) {
  const queryClient = useQueryClient()
  return (update: (list: TaskAttachment[]) => TaskAttachment[], countChange: 1 | -1) => {
    queryClient.setQueryData<TaskAttachment[]>(taskAttachmentsKey(code), (current) => current && update(current))
    queryClient.setQueryData<Task>(taskKey(code), (task) =>
      task?.attachments_count === undefined ? task : { ...task, attachments_count: Math.max(0, task.attachments_count + countChange) },
    )
    void refreshQueries(queryClient, { later: [...taskListsKeys, taskActivityKey(code)] })
  }
}

export function useTaskAttachments(code: string) {
  return useQuery({ queryKey: taskAttachmentsKey(code), queryFn: () => fetchTaskAttachments(code) })
}

export function useDeleteAttachment(code: string) {
  const store = useStoreAttachments(code)
  return useMutation({
    mutationFn: (attachment: TaskAttachment) => deleteTaskAttachment(attachment.uuid),
    onSuccess: (_result, attachment) => {
      toast.success(`«${attachment.name}» borrado.`)
      store((list) => list.filter((item) => item.uuid !== attachment.uuid), -1)
    },
  })
}

/** Several files at once, each with its bar; refused ones stay listed with the reason. */
export function useAttachmentUploads(code: string, existing: number) {
  const store = useStoreAttachments(code)
  // Room left for this batch, spent as each file passes the other checks.
  const slots = useRef(0)
  const queue = useUploadQueue<TaskAttachment>({
    validate: (file) => attachmentProblem(file) ?? (slots.current-- > 0 ? null : TOO_MANY_ATTACHMENTS),
    send: (file, onProgress) => uploadTaskAttachment(code, file, onProgress),
    failureTitle: 'No se pudo adjuntar',
    keepFailures: true,
    onUploaded: (attachment) => {
      toast.success(`«${attachment.name}» adjuntado.`)
      store((list) => [attachment, ...list], 1)
    },
  })

  function add(files: File[]) {
    slots.current = MAX_ATTACHMENTS_PER_TASK - existing - queue.inFlight
    for (const file of files) queue.start(file)
  }

  return { uploads: queue.items, add, retry: queue.retry, dismiss: queue.dismiss }
}
