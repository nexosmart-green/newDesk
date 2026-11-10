import { api, ApiError, toApiError } from '@/lib/api'
import type { Envelope } from './types'

// API espejo del panel Go (ADR-0002 punto 10): `api/<ruta>` de monitoreo vive en `/api/v1/monitoring/<ruta>` y
// `api/portal/<ruta>` (el portal del cliente) en `/api/v1/portal/<ruta>`, con el mismo request y la misma respuesta.
// Las rutas van sin el `api/`: 'servers', 'portal-admin/projects', 'portal/projects'.
// Los errores llegan como ApiError (422 con errores por campo).
async function request<T>(send: Promise<{ data: Envelope<T> }>): Promise<Envelope<T>> {
  try {
    return (await send).data
  } catch (error) {
    throw toApiError(error)
  }
}

const url = (path: string) => (path.startsWith('portal/') ? `/${path}` : `/monitoring/${path}`)

export const monitoringApi = {
  get: <T>(path: string) => request<T>(api.get(url(path))).then((envelope) => envelope.data),
  getEnvelope: <T>(path: string) => request<T>(api.get(url(path))),
  post: <T>(path: string, body?: unknown) => request<T>(api.post(url(path), body ?? {})).then((envelope) => envelope.data),
  // El sobre entero: el message avisa algo además del dato (el bot no pudo crear el canal, la prueba de Discord).
  postEnvelope: <T>(path: string, body?: unknown) => request<T>(api.post(url(path), body ?? {})),
  put: <T>(path: string, body: unknown) => request<T>(api.put(url(path), body)).then((envelope) => envelope.data),
  putEnvelope: <T>(path: string, body: unknown) => request<T>(api.put(url(path), body)),
  del: <T>(path: string) => request<T>(api.delete(url(path))).then((envelope) => envelope.data),
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.message
  if (error instanceof Error) return error.message
  return 'Algo falló.'
}

// Los errores de esos campos juntos, o el mensaje general si el back no marcó ninguno.
export function fieldError(error: unknown, ...fields: string[]): string | null {
  if (!(error instanceof ApiError)) return null
  return fields.map((field) => error.field(field)).filter(Boolean).join(' ') || error.message
}

// Las imágenes (logos, capturas, adjuntos del chat) llegan como rutas de la API de monitoreo: `api/<ruta>` del
// equipo vive en /api/v1/monitoring/<ruta> y `api/portal/<ruta>` en /api/v1/portal/<ruta> (ADR-0002 punto 10).
// Una ruta que ya viene con /api/v1/ queda tal cual.
export function apiUrl(path: string): string {
  const clean = path.replace(/^\/+/, '')
  if (clean.startsWith('api/v1/')) return `/${clean}`
  if (clean.startsWith('api/portal/')) return `/api/v1/${clean.slice('api/'.length)}`
  if (clean.startsWith('api/')) return `/api/v1/monitoring/${clean.slice('api/'.length)}`
  return `/${clean}`
}
