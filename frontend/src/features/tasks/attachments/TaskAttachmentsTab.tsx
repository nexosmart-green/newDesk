import { useState } from 'react'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Lightbox } from '@/components/ui/Lightbox'
import { PageState } from '@/components/ui/PageState'
import { Skeleton } from '@/components/ui/Skeleton'
import type { TaskAttachment } from '../types'
import { UploadProgressList } from '../uploads/UploadProgressList'
import { AttachmentCard } from './AttachmentCard'
import { AttachmentDropzone } from './AttachmentDropzone'
import { useAttachmentUploads, useDeleteAttachment, useTaskAttachments } from './useAttachments'

const gridClass = 'grid gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4'

function AttachmentsSkeleton() {
  return (
    <div role="status" aria-live="polite" className="space-y-4">
      <span className="sr-only">Cargando archivos…</span>
      <Skeleton className="h-24 w-full rounded-lg" />
      <div className={gridClass}>
        {Array.from({ length: 4 }, (_, index) => (
          <div key={index} className="flex overflow-hidden rounded-lg border border-border bg-surface sm:flex-col">
            <Skeleton className="aspect-square w-20 shrink-0 rounded-none sm:aspect-[4/3] sm:w-full" />
            <div className="flex-1 space-y-2 p-2.5">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-3 w-1/2" />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

/** Tab "Archivos" (RF-04.09): drop or pick files, see them as a grid, open images in the viewer. */
export function TaskAttachmentsTab({ code }: { code: string }) {
  const attachments = useTaskAttachments(code)
  const list = attachments.data ?? []
  const uploads = useAttachmentUploads(code, list.length)
  const remove = useDeleteAttachment(code)
  const [deleting, setDeleting] = useState<TaskAttachment | null>(null)
  const [viewing, setViewing] = useState<number | null>(null)
  const images = list.filter((attachment) => attachment.is_image)

  if (!attachments.data) {
    if (attachments.isPending) return <AttachmentsSkeleton />
    return <PageState isPending={false} error={attachments.error} onRetry={() => void attachments.refetch()} />
  }

  const empty = list.length === 0 && uploads.uploads.length === 0

  return (
    <div className="space-y-4">
      <AttachmentDropzone onFiles={uploads.add} prominent={empty} />
      <UploadProgressList uploads={uploads.uploads} onRetry={uploads.retry} onDismiss={uploads.dismiss} />
      {list.length > 0 && (
        <ul aria-label="Archivos adjuntos" className={gridClass}>
          {list.map((attachment) => (
            <AttachmentCard
              key={attachment.uuid}
              attachment={attachment}
              onOpenImage={() => setViewing(images.findIndex((image) => image.uuid === attachment.uuid))}
              onDelete={() => setDeleting(attachment)}
            />
          ))}
        </ul>
      )}
      {viewing !== null && images.length > 0 && (
        <Lightbox
          images={images.map((image) => ({ src: image.url, name: image.name }))}
          index={viewing}
          onIndexChange={setViewing}
          onClose={() => setViewing(null)}
        />
      )}
      {deleting && (
        <ConfirmDialog
          title="Borrar archivo"
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(deleting, { onSuccess: () => setDeleting(null) })}
          onClose={() => {
            setDeleting(null)
            remove.reset()
          }}
        >
          <p>
            Se va a borrar «{deleting.name}» de la tarea para todos. No se puede recuperar.
          </p>
        </ConfirmDialog>
      )}
    </div>
  )
}
