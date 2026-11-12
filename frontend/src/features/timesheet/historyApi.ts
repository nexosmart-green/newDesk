import { AxiosError } from 'axios'
import { api, ApiError, queryParams, timedPageOf, toApiError, type ApiEnvelope } from '@/lib/api'
import type { HistoryFilters, MyTimeEntriesPage } from './types'

function historyParams(filters: HistoryFilters) {
  return queryParams({ ...filters })
}

/** "Historial": my entries (or someone's, with `time.manage`) in a free range, 50 per page, newest first. */
export async function fetchMyTimeEntries(filters: HistoryFilters, page: number): Promise<MyTimeEntriesPage> {
  return timedPageOf(api.get<MyTimeEntriesPage>('/me/time-entries', { params: { ...historyParams(filters), page } }))
}

/** A blob response hides the JSON error envelope: read it back so the toast says the backend's reason. */
async function blobErrorOf(error: unknown): Promise<ApiError> {
  if (error instanceof AxiosError && error.response?.data instanceof Blob) {
    try {
      const body = JSON.parse(await error.response.data.text()) as Partial<ApiEnvelope<unknown>>
      return new ApiError(error.response.status, body.message ?? '', body.errors ?? {})
    } catch {
      // Not JSON (an HTML error page): `errorTextOf` words it by status.
      return new ApiError(error.response.status, '')
    }
  }
  return toApiError(error)
}

function filenameOf(disposition: unknown, fallback: string): string {
  const match = typeof disposition === 'string' ? /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition) : null
  return match?.[1] ? decodeURIComponent(match[1]) : fallback
}

/** Same filters as the list; the server builds the CSV (`;`, UTF-8 with BOM) so it covers every page. */
export async function exportMyTimeEntries(filters: HistoryFilters): Promise<{ blob: Blob; filename: string }> {
  try {
    const response = await api.get<Blob>('/me/time-entries/export', {
      params: historyParams(filters),
      responseType: 'blob',
      headers: { Accept: 'text/csv, application/json' },
    })
    return { blob: response.data, filename: filenameOf(response.headers['content-disposition'], `horas-${filters.from}-a-${filters.to}.csv`) }
  } catch (error) {
    throw await blobErrorOf(error)
  }
}
