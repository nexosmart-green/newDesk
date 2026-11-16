import { FileSpreadsheet, FileText } from 'lucide-react'
import { formatFileSize } from '@/lib/fileSize'
import type { AssistantFile } from './types'

/** RF-13.05: the reports attached to an answer. Plain links: the session cookie authorizes the download. */
export function FileLinks({ files }: { files: AssistantFile[] }) {
  if (files.length === 0) return null

  return (
    <ul className="flex flex-wrap gap-2" aria-label="Reportes para descargar">
      {files.map((file) => {
        const Icon = file.format === 'xlsx' ? FileSpreadsheet : FileText
        return (
          <li key={file.uuid}>
            <a
              href={file.url}
              download={file.name}
              className="inline-flex min-h-9 items-center gap-2 rounded-md border border-line bg-surface px-3 py-1.5 text-sm hover:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand coarse:min-h-11"
            >
              <Icon aria-hidden="true" className="size-4 shrink-0 text-accent" strokeWidth={1.75} />
              <span className="font-medium">Descargar {file.name}</span>
              <span className="tabular whitespace-nowrap text-muted">{formatFileSize(file.size)}</span>
            </a>
          </li>
        )
      })}
    </ul>
  )
}
