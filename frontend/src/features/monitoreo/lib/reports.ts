import type { ReportStatus, ReportUrgency } from '../api/types'
import type { Tone } from './status'

// Las tres opciones que ve el cliente; "No puedo trabajar" además avisa por Discord al equipo.
export const urgencyMeta: Record<ReportUrgency, { label: string; hint: string; tone: Tone }> = {
  blocking: { label: 'No puedo trabajar', hint: 'Algo importante no anda y me frena.', tone: 'crit' },
  annoying: { label: 'Molesta pero puedo seguir', hint: 'Algo anda mal, pero puedo seguir trabajando.', tone: 'warn' },
  question: { label: 'Consulta', hint: 'Una duda o un pedido, sin apuro.', tone: 'idle' },
}

export const reportStatusMeta: Record<ReportStatus, { label: string; tone: Tone }> = {
  received: { label: 'Recibido', tone: 'info' },
  in_review: { label: 'En revisión', tone: 'warn' },
  resolved: { label: 'Resuelto', tone: 'ok' },
}

// Mismas reglas que el back: PNG, JPEG o WebP de hasta 5 MB (el back además mira el contenido).
export const SCREENSHOT_TYPES = ['image/png', 'image/jpeg', 'image/webp']
export const SCREENSHOT_MAX_BYTES = 5 * 1024 * 1024

// subject: "La captura" en el reporte, "La imagen" en el chat.
export function screenshotProblem(file: { type: string; size: number }, subject = 'La captura'): string | null {
  if (!SCREENSHOT_TYPES.includes(file.type)) return `${subject} tiene que ser PNG, JPG o WebP.`
  if (file.size > SCREENSHOT_MAX_BYTES) return `${subject} puede pesar hasta 5 MB.`
  return null
}

export const DESCRIPTION_MIN = 10
export const DESCRIPTION_MAX = 2000

// Chat de cada reporte (D-049): mismas reglas que el back.
export const MESSAGE_MAX = 4000
export const MESSAGE_IMAGES_MAX = 3
