import { CircleAlert, LoaderCircle, RotateCw, X } from 'lucide-react'
import type { UploadItem } from './useUploadQueue'

interface UploadProgressListProps {
  uploads: UploadItem[]
  label?: string
  onRetry?: (id: number) => void
  onDismiss?: (id: number) => void
}

const iconButton =
  'grid size-9 shrink-0 cursor-pointer place-items-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent'

/** One row per file in flight (bar + percent); refused ones keep their reason until dismissed. */
export function UploadProgressList({ uploads, label = 'Archivos subiendo', onRetry, onDismiss }: UploadProgressListProps) {
  if (uploads.length === 0) return null
  return (
    <ul className="space-y-1.5" aria-label={label}>
      {uploads.map((upload) =>
        upload.error === null ? (
          <li key={upload.id} className="flex min-h-9 items-center gap-2 text-xs text-muted">
            <LoaderCircle aria-hidden="true" className="size-3.5 shrink-0 animate-spin text-accent" />
            <span className="min-w-0 flex-1 truncate">Subiendo {upload.name}</span>
            <progress
              value={upload.percent}
              max={100}
              aria-label={`Progreso de ${upload.name}`}
              className="h-1.5 w-20 overflow-hidden rounded-full sm:w-28 [&::-moz-progress-bar]:bg-brand [&::-webkit-progress-bar]:bg-surface-2 [&::-webkit-progress-value]:bg-brand"
            />
            <span className="tabular w-9 text-right">{upload.percent}%</span>
          </li>
        ) : (
          <li key={upload.id} className="flex items-center gap-2 rounded-lg border border-danger/40 bg-danger/10 py-1 pr-1 pl-3 text-sm">
            <CircleAlert aria-hidden="true" className="size-4 shrink-0 text-danger" />
            <p className="min-w-0 flex-1">
              <span className="block truncate font-medium">{upload.name}</span>
              <span role="alert" className="block text-xs text-danger">
                {upload.error}
              </span>
            </p>
            {upload.retryable && onRetry && (
              <button type="button" className={iconButton} aria-label={`Reintentar ${upload.name}`} title="Reintentar" onClick={() => onRetry(upload.id)}>
                <RotateCw aria-hidden="true" className="size-4" />
              </button>
            )}
            {onDismiss && (
              <button type="button" className={iconButton} aria-label={`Descartar ${upload.name}`} title="Descartar" onClick={() => onDismiss(upload.id)}>
                <X aria-hidden="true" className="size-4" />
              </button>
            )}
          </li>
        ),
      )}
    </ul>
  )
}
