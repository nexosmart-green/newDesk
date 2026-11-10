import { useState } from 'react'
import { toast } from '@/components/ui/toast/toastStore'
import { toastTextOf } from '@/lib/errorText'
import { uploadTaskImage } from '../tasksApi'

export interface ImageUpload {
  id: number
  name: string
  percent: number
}

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

let nextUploadId = 1

/** Pasted/dropped images: one upload per file, each with its own progress. */
export function useImageUploads(projectId: number | undefined) {
  const [uploads, setUploads] = useState<ImageUpload[]>([])
  const patch = (id: number, percent: number) =>
    setUploads((current) => current.map((item) => (item.id === id ? { ...item, percent } : item)))

  /** Resolves with the image URL, or null when it was refused (already explained with a toast). */
  async function upload(file: File, id: number): Promise<string | null> {
    const problem = imageProblem(file)
    if (problem) {
      toast.error(problem, { title: 'No se pudo subir la imagen' })
      return null
    }
    if (!projectId) return null
    setUploads((current) => [...current, { id, name: file.name || 'imagen', percent: 0 }])
    try {
      const url = await uploadTaskImage(projectId, file, (percent) => patch(id, percent))
      return url
    } catch (error) {
      const text = toastTextOf(error)
      toast.error(text.message, { title: 'No se pudo subir la imagen' })
      return null
    } finally {
      setUploads((current) => current.filter((item) => item.id !== id))
    }
  }

  return { uploads, upload, nextId: () => nextUploadId++, canUpload: Boolean(projectId) }
}
