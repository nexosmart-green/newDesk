import axios, { AxiosError } from 'axios'
import { claimFieldError } from './fieldErrors'

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
      // HTML pages (proxy 413/502) and empty bodies carry no message: `errorTextOf` words it by status.
      body?.message ?? '',
      body?.errors ?? {},
      businessCodeOf(body?.data),
    )
  }
  // No response: `code` keeps axios' reason (ECONNABORTED, ERR_NETWORK…) for `errorTextOf`.
  return new ApiError(0, '', {}, error instanceof AxiosError ? (error.code ?? null) : null)
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
  return error instanceof ApiError ? claimFieldError(error, field) : undefined
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

/** Time-entry lists also total the whole filter (not just the page) in `meta.total_seconds`. */
export interface TimedPage<T> {
  data: T[]
  meta: Paginated<T>['meta'] & { total_seconds: number }
}

export async function timedPageOf<T>(request: Promise<{ data: TimedPage<T> }>): Promise<TimedPage<T>> {
  try {
    const response = await request
    return { data: response.data.data, meta: response.data.meta }
  } catch (error) {
    throw toApiError(error)
  }
}
