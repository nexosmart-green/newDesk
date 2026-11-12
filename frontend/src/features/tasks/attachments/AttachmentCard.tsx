import { Download, Trash2 } from 'lucide-react'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { formatInstant } from '@/lib/dates'
import { formatFileSize } from '@/lib/fileSize'
import type { TaskAttachment } from '../types'
import { fileKindOf, opensInline } from './fileKinds'

interface AttachmentCardProps {
  attachment: TaskAttachment
  onOpenImage: () => void
  onDelete: () => void
}

const actionClass =
  'grid size-9 shrink-0 cursor-pointer place-items-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent'

/** Thumbnail (or type tile), name, size, who and when; download and, when allowed, delete. A row on phones, a tile from `sm`. */
export function AttachmentCard({ attachment, onOpenImage, onDelete }: AttachmentCardProps) {
  const timeZone = useProfileTimeZone()
  const kind = fileKindOf(attachment.name, attachment.is_image)
  const Icon = kind.icon
  const previewClass =
    'group/preview relative block aspect-square w-20 shrink-0 cursor-pointer overflow-hidden border-r border-border bg-surface-2 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent sm:aspect-[4/3] sm:w-full sm:border-r-0 sm:border-b'

  return (
    <li className="flex min-w-0 overflow-hidden rounded-lg border border-border bg-surface sm:flex-col">
      {attachment.is_image ? (
        <button type="button" onClick={onOpenImage} className={previewClass} aria-label={`Ver ${attachment.name}`}>
          <img
            src={attachment.url}
            alt=""
            loading="lazy"
            decoding="async"
            className="size-full object-cover transition-transform duration-300 group-hover/preview:scale-[1.03] motion-reduce:transition-none"
          />
        </button>
      ) : (
        <a
          href={attachment.url}
          target={opensInline(attachment.mime, false) ? '_blank' : undefined}
          rel="noopener noreferrer"
          download={opensInline(attachment.mime, false) ? undefined : attachment.name}
          className={`${previewClass} grid place-items-center`}
          aria-label={`Abrir ${attachment.name}`}
        >
          <span className="flex flex-col items-center gap-1.5">
            <Icon aria-hidden="true" className={`size-7 sm:size-9 ${kind.tone}`} strokeWidth={1.5} />
            {kind.extension && <span className="text-xs font-semibold tracking-wide text-muted uppercase">{kind.extension}</span>}
          </span>
        </a>
      )}
      <div className="flex min-w-0 flex-1 items-start gap-1 p-2.5 pr-1">
        <div className="min-w-0 flex-1 space-y-0.5">
          <p className="truncate text-sm font-medium" title={attachment.name}>
            {attachment.name}
          </p>
          <p className="tabular text-xs text-muted">{formatFileSize(attachment.size)}</p>
          <p className="truncate text-xs text-muted">
            {attachment.uploaded_by?.full_name ?? 'Alguien'},{' '}
            <time dateTime={attachment.created_at}>{formatInstant(attachment.created_at, timeZone)}</time>
          </p>
        </div>
        <div className="flex shrink-0 flex-col">
          <a href={attachment.url} download={attachment.name} className={actionClass} aria-label={`Descargar ${attachment.name}`} title="Descargar">
            <Download aria-hidden="true" className="size-4" />
          </a>
          {attachment.can_delete && (
            <button type="button" onClick={onDelete} className={`${actionClass} hover:text-danger`} aria-label={`Borrar ${attachment.name}`} title="Borrar">
              <Trash2 aria-hidden="true" className="size-4" />
            </button>
          )}
        </div>
      </div>
    </li>
  )
}
