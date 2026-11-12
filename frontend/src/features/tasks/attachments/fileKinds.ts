import { File, FileArchive, FileImage, FileSpreadsheet, FileText, Presentation, type LucideIcon } from 'lucide-react'
import { fileExtension } from '@/lib/fileSize'

interface FileKind {
  icon: LucideIcon
  /** Tint of the icon tile; colors say the family, the extension label says it in words. */
  tone: string
}

const kinds: Record<string, FileKind> = {
  pdf: { icon: FileText, tone: 'text-danger' },
  docx: { icon: FileText, tone: 'text-accent' },
  odt: { icon: FileText, tone: 'text-accent' },
  txt: { icon: FileText, tone: 'text-muted' },
  xlsx: { icon: FileSpreadsheet, tone: 'text-success' },
  ods: { icon: FileSpreadsheet, tone: 'text-success' },
  csv: { icon: FileSpreadsheet, tone: 'text-success' },
  pptx: { icon: Presentation, tone: 'text-warning' },
  zip: { icon: FileArchive, tone: 'text-muted' },
}

export function fileKindOf(name: string, isImage: boolean): FileKind & { extension: string } {
  const extension = fileExtension(name)
  const kind = kinds[extension] ?? (isImage ? { icon: FileImage, tone: 'text-accent' } : { icon: File, tone: 'text-muted' })
  return { ...kind, extension }
}

/** Images and PDF open in the browser; the rest download. */
export function opensInline(mime: string, isImage: boolean): boolean {
  return isImage || mime === 'application/pdf'
}
