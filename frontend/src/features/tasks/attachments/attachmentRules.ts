import { fileExtension } from '@/lib/fileSize'

export const MAX_ATTACHMENT_BYTES = 20 * 1024 * 1024
export const MAX_ATTACHMENTS_PER_TASK = 50

/** What the backend accepts (it checks the content; this is the early, friendly check by name). */
export const ATTACHMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'txt', 'csv', 'zip']
export const ATTACHMENT_ACCEPT = ATTACHMENT_EXTENSIONS.map((extension) => `.${extension}`).join(',')

/** Same messages as the backend, so the reason reads the same before and after uploading. */
export function attachmentProblem(file: File): string | null {
  const extension = fileExtension(file.name)
  if (extension === 'heic' || extension === 'heif' || /hei[cf]/i.test(file.type)) return 'Convertí la imagen a JPG o PNG.'
  if (!ATTACHMENT_EXTENSIONS.includes(extension)) return 'Ese tipo de archivo no se puede adjuntar.'
  if (file.size > MAX_ATTACHMENT_BYTES) return 'El archivo puede pesar hasta 20 MB.'
  return null
}

export const TOO_MANY_ATTACHMENTS = `La tarea ya tiene ${MAX_ATTACHMENTS_PER_TASK} archivos, el máximo. Borrá alguno para subir otro.`
