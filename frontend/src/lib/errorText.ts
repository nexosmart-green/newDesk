import { ApiError, toApiError } from './api'
import { unclaimedFieldMessage } from './fieldErrors'

export const MODULE_DISABLED = 'module_disabled'

/** axios' `code` when no response arrived (see `toApiError`). */
const TIMEOUT_CODES = new Set(['ECONNABORTED', 'ETIMEDOUT'])

export interface ErrorText {
  title: string
  message: string
}

function asApiError(error: unknown): ApiError {
  return error instanceof ApiError ? error : toApiError(error)
}

function isOffline(): boolean {
  return typeof navigator !== 'undefined' && navigator.onLine === false
}

/** 401 while using the app: the session cookie expired or was revoked elsewhere. */
export function isSessionLost(error: unknown): boolean {
  return asApiError(error).status === 401
}

export const SESSION_LOST_TEXT: ErrorText = {
  title: 'Tu sesión se cerró',
  message: 'Tu sesión se cerró. Ingresá de nuevo para seguir.',
}

/** Network down, 5xx, expired session: things the person didn't cause and can't fix by editing the form. */
export function isUnexpectedError(error: unknown): boolean {
  const apiError = asApiError(error)
  return apiError.status === 0 || apiError.status >= 500 || apiError.status === 419
}

/** Transport-level failures: words depend on the status alone, never on the (missing or HTML) body. */
function transportTextOf(apiError: ApiError): ErrorText | null {
  if (apiError.status === 0) {
    if (apiError.code && TIMEOUT_CODES.has(apiError.code)) {
      return { title: 'Sin respuesta del servidor', message: 'El servidor tardó demasiado en responder. Probá de nuevo.' }
    }
    if (isOffline()) {
      return { title: 'Sin conexión', message: 'Sin conexión a internet. Revisá la red y probá de nuevo.' }
    }
    return { title: 'Sin conexión con el servidor', message: 'No se pudo llegar al servidor. Revisá la red y probá de nuevo.' }
  }
  if (apiError.status === 401) return SESSION_LOST_TEXT
  if (apiError.status === 419) {
    return { title: 'La página quedó vieja', message: 'Pasó mucho tiempo sin actividad. Recargá la página para seguir.' }
  }
  if (apiError.status === 413) {
    return { title: 'Archivo demasiado grande', message: 'El archivo es demasiado grande para subirlo.' }
  }
  if (apiError.status >= 500) {
    return {
      title: 'El servidor no pudo completar la acción',
      message: 'No se guardó nada. Probá de nuevo en unos segundos; si sigue pasando, avisá a soporte.',
    }
  }
  return null
}

const titleByStatus: Record<number, string> = {
  403: 'No tenés permiso',
  404: 'Ya no existe',
  409: 'Cambió mientras lo editabas',
}

/** What happened + what to do next, in the app's voice (no apologies, never just "Error"). */
export function errorTextOf(error: unknown): ErrorText {
  const apiError = asApiError(error)
  const transport = transportTextOf(apiError)
  if (transport) return transport
  if (apiError.code === MODULE_DISABLED) {
    return { title: 'Módulo no disponible', message: 'Tu rol no tiene acceso a este módulo. Pedíselo a un administrador.' }
  }
  const title = titleByStatus[apiError.status] ?? 'No se pudo completar'
  // An HTML page or an empty body: nothing the backend explained.
  const message = apiError.message.trim() || 'No se pudo completar la acción. Recargá la página y probá de nuevo.'
  return { title, message }
}

/** For flows that already closed their form (optimistic): the first field message, else the general text. */
export function toastTextOf(error: unknown): ErrorText {
  const apiError = asApiError(error)
  const firstFieldMessage = Object.values(apiError.fieldErrors)[0]?.[0]
  return firstFieldMessage ? { title: 'No se pudo completar', message: firstFieldMessage } : errorTextOf(apiError)
}

/** A validation error whose key no input on screen shows (`ids.0`, `permissions.5`…): its first message. */
export function hiddenFieldErrorTextOf(error: ApiError): ErrorText | null {
  const message = unclaimedFieldMessage(error)
  return message ? { title: 'Revisá los datos', message } : null
}
