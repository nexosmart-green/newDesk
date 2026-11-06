import axios, { AxiosError } from 'axios'

/** Every backend response follows `{ data, message, errors }`. */
export interface ApiEnvelope<T> {
  data: T
  message: string | null
  errors: Record<string, string[]> | null
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

export async function ensureCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[]>

  constructor(status: number, message: string, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.fieldErrors = fieldErrors
  }

  /** First error of a field; for arrays Laravel keys them per item (`images.0`), so those count too. */
  field(name: string): string | undefined {
    return this.fieldErrors[name]?.[0] ?? Object.entries(this.fieldErrors).find(([key]) => key.startsWith(`${name}.`))?.[1][0]
  }
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof AxiosError && error.response) {
    const body = error.response.data as Partial<ApiEnvelope<unknown>> | undefined
    return new ApiError(
      error.response.status,
      body?.message ?? 'Error inesperado',
      body?.errors ?? {},
    )
  }
  return new ApiError(0, 'No se pudo conectar con el servidor')
}
