import { ApiError, toApiError } from './api'

export const MODULE_DISABLED = 'module_disabled'

export interface ErrorText {
  title: string
  message: string
}

/** Network down, 5xx, expired session: things the person didn't cause and can't fix by editing the form. */
export function isUnexpectedError(error: unknown): boolean {
  const apiError = error instanceof ApiError ? error : toApiError(error)
  return apiError.status === 0 || apiError.status >= 500 || apiError.status === 419
}

/** What happened + what to do next, in the app's voice (no apologies, never just "Error"). */
export function errorTextOf(error: unknown): ErrorText {
  const apiError = error instanceof ApiError ? error : toApiError(error)
  if (apiError.status === 0) {
    return { title: 'Sin conexión con el servidor', message: 'Revisá tu conexión a internet y probá de nuevo.' }
  }
  if (apiError.status === 419) {
    return { title: 'La sesión venció', message: 'Recargá la página e ingresá de nuevo para seguir.' }
  }
  if (apiError.status >= 500) {
    return {
      title: 'El servidor no pudo completar la acción',
      message: 'No se guardó nada. Probá de nuevo en unos segundos; si sigue pasando, avisá a soporte.',
    }
  }
  if (apiError.code === MODULE_DISABLED) {
    return { title: 'Módulo no disponible', message: 'Tu rol no tiene acceso a este módulo. Pedíselo a un administrador.' }
  }
  if (apiError.status === 403) {
    return { title: 'No tenés permiso', message: apiError.message }
  }
  return { title: 'No se pudo completar', message: apiError.message }
}

/** For flows that already closed their form (optimistic): the first field message, else the general text. */
export function toastTextOf(error: unknown): ErrorText {
  const apiError = error instanceof ApiError ? error : toApiError(error)
  const firstFieldMessage = Object.values(apiError.fieldErrors)[0]?.[0]
  return firstFieldMessage ? { title: 'No se pudo completar', message: firstFieldMessage } : errorTextOf(apiError)
}
