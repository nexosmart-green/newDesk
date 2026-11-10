import { LoaderCircle } from 'lucide-react'
import type { ImageUpload } from './useImageUploads'

/** One bar per image in flight; the text keeps a marker where each one will land. */
export function UploadProgressList({ uploads }: { uploads: ImageUpload[] }) {
  if (uploads.length === 0) return null
  return (
    <ul className="space-y-1.5" aria-label="Imágenes subiendo">
      {uploads.map((upload) => (
        <li key={upload.id} className="flex items-center gap-2 text-xs text-muted">
          <LoaderCircle aria-hidden="true" className="size-3.5 shrink-0 animate-spin text-accent" />
          <span className="min-w-0 flex-1 truncate">Subiendo {upload.name}</span>
          <progress
            value={upload.percent}
            max={100}
            aria-label={`Progreso de ${upload.name}`}
            className="h-1.5 w-24 overflow-hidden rounded-full [&::-moz-progress-bar]:bg-brand [&::-webkit-progress-bar]:bg-surface-2 [&::-webkit-progress-value]:bg-brand"
          />
          <span className="tabular w-9 text-right">{upload.percent}%</span>
        </li>
      ))}
    </ul>
  )
}
