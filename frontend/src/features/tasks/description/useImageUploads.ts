import { uploadTaskImage } from '../tasksApi'
import { useUploadQueue } from '../uploads/useUploadQueue'

export const MAX_IMAGE_BYTES = 5 * 1024 * 1024
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
export const ACCEPT_ATTRIBUTE = ACCEPTED_TYPES.join(',')

/** Same rules as the backend, checked first so a wrong file never waits for a round trip. */
export function imageProblem(file: File): string | null {
  if (/\.hei[cf]$/i.test(file.name) || /hei[cf]/i.test(file.type)) return 'Convertí la imagen a JPG o PNG.'
  if (!ACCEPTED_TYPES.includes(file.type)) return `«${file.name}» no es una imagen JPG, PNG, WEBP o GIF.`
  if (file.size > MAX_IMAGE_BYTES) return `«${file.name}» pesa más de 5 MB.`
  return null
}

/** Pasted/dropped markdown images, stored per project: each upload resolves with its relative URL. */
export function useImageUploads(projectId: number | undefined) {
  const queue = useUploadQueue<string>({
    validate: imageProblem,
    send: (file, onProgress) => uploadTaskImage(projectId ?? 0, file, onProgress),
    failureTitle: 'No se pudo subir la imagen',
  })
  return { uploads: queue.items, start: queue.start, canUpload: Boolean(projectId) }
}
