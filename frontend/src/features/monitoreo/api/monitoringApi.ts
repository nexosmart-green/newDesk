import { api, ApiError, toApiError } from '@/lib/api'
import type { Envelope } from './types'

// API espejo del panel Go (ADR-0002 punto 10): `api/<ruta>` de monitoreo vive en `/api/v1/monitoring/<ruta>`,
// con el mismo request y la misma respuesta. Los errores llegan como ApiError (422 con errores por campo).
async function request<T>(send: Promise<{ data: Envelope<T> }>): Promise<Envelope<T>> {
  try {
    return (await send).data
  } catch (error) {
    throw toApiError(error)
  }
}

const url = (path: string) => `/monitoring/${path}`

export const monitoringApi = {
  get: <T>(path: string) => request<T>(api.get(url(path))).then((envelope) => envelope.data),
  getEnvelope: <T>(path: string) => request<T>(api.get(url(path))),
  post: <T>(path: string, body?: unknown) => request<T>(api.post(url(path), body ?? {})).then((envelope) => envelope.data),
  put: <T>(path: string, body: unknown) => request<T>(api.put(url(path), body)).then((envelope) => envelope.data),
  del: <T>(path: string) => request<T>(api.delete(url(path))).then((envelope) => envelope.data),
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.message
  if (error instanceof Error) return error.message
  return 'Algo falló.'
}
