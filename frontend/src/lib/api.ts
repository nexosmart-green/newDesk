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
  /** Stable business-rule identifier sent by the backend in `data.code` (e.g. `last_admin`). */
  readonly code: string | null

  constructor(status: number, message: string, fieldErrors: Record<string, string[]> = {}, code: string | null = null) {
    super(message)
    this.status = status
    this.fieldErrors = fieldErrors
    this.code = code
  }

  hasFieldErrors(): boolean {
    return Object.keys(this.fieldErrors).length > 0
  }

  /** First error of a field; for arrays Laravel keys them per item (`images.0`), so those count too. */
  field(name: string): string | undefined {
    return this.fieldErrors[name]?.[0] ?? Object.entries(this.fieldErrors).find(([key]) => key.startsWith(`${name}.`))?.[1][0]
  }
}

function businessCodeOf(data: unknown): string | null {
  if (typeof data !== 'object' || data === null || !('code' in data)) return null
  return typeof data.code === 'string' ? data.code : null
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error
  if (error instanceof AxiosError && error.response) {
    const body = error.response.data as Partial<ApiEnvelope<unknown>> | undefined
    return new ApiError(
      error.response.status,
      body?.message ?? 'Error inesperado',
      body?.errors ?? {},
      businessCodeOf(body?.data),
    )
  }
  return new ApiError(0, 'No se pudo conectar con el servidor')
}

/** Awaits a request and returns the whole envelope, normalizing failures to `ApiError`. */
export async function envelopeOf<T>(request: Promise<{ data: ApiEnvelope<T> }>): Promise<ApiEnvelope<T>> {
  try {
    const response = await request
    return response.data
  } catch (error) {
    throw toApiError(error)
  }
}

/** Awaits a request and returns the envelope's `data`. */
export async function dataOf<T>(request: Promise<{ data: ApiEnvelope<T> }>): Promise<T> {
  return (await envelopeOf(request)).data
}

export function fieldErrorOf(error: unknown, field: string): string | undefined {
  return error instanceof ApiError ? error.fieldErrors[field]?.[0] : undefined
}

/** Awaits a paginated Laravel resource collection (`{ data, meta, links }`) and keeps `data` + `meta`. */
export async function pageOf<T>(request: Promise<{ data: Paginated<T> }>): Promise<Paginated<T>> {
  try {
    const response = await request
    return { data: response.data.data, meta: response.data.meta }
  } catch (error) {
    throw toApiError(error)
  }
}

type QueryValue = string | number | boolean | null | undefined

/** Drops empty filters so the query string only carries what the user chose. */
export function queryParams(values: Record<string, QueryValue>): Record<string, string | number> {
  const params: Record<string, string | number> = {}
  for (const [key, value] of Object.entries(values)) {
    if (value === null || value === undefined || value === false) continue
    if (typeof value === 'string' && value.trim() === '') continue
    params[key] = value === true ? 1 : typeof value === 'string' ? value.trim() : value
  }
  return params
}
