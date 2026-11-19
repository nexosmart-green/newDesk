import { AxiosError } from 'axios'
import { api, ApiError, dataOf, pageOf, queryParams, toApiError, type ApiEnvelope } from '@/lib/api'
import type {
  AllocationInput,
  DailyHours,
  DailyHoursFilters,
  EntryPage,
  ExtensionRequest,
  HourBag,
  MyEntriesPage,
  MyEntryFilters,
  ProjectEntryFilters,
  ProjectHours,
} from './types'

export async function fetchProjectHours(projectId: number): Promise<ProjectHours> {
  return dataOf(api.get<ApiEnvelope<ProjectHours>>(`/projects/${projectId}/hours`))
}

export async function fetchProjectEntries(projectId: number, filters: ProjectEntryFilters): Promise<EntryPage> {
  return pageOf(api.get<EntryPage>(`/projects/${projectId}/time-entries`, { params: queryParams({ ...filters }) }))
}

/** "Mis horas": my entries across projects (or someone else's with `time.manage`), 50 per page, newest first. */
export async function fetchEntries(filters: MyEntryFilters, page: number): Promise<MyEntriesPage> {
  return pageOf(api.get<MyEntriesPage>('/time-entries', { params: { ...queryParams({ ...filters }), page } }))
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
export async function exportEntries(filters: MyEntryFilters): Promise<{ blob: Blob; filename: string }> {
  try {
    const response = await api.get<Blob>('/time-entries/export', {
      params: queryParams({ ...filters }),
      responseType: 'blob',
      headers: { Accept: 'text/csv, application/json' },
    })
    return { blob: response.data, filename: filenameOf(response.headers['content-disposition'], `horas-${filters.from}-a-${filters.to}.csv`) }
  } catch (error) {
    throw await blobErrorOf(error)
  }
}

export async function fetchDailyHours(filters: DailyHoursFilters): Promise<DailyHours> {
  return dataOf(api.get<ApiEnvelope<DailyHours>>('/time-entries/days', { params: queryParams({ ...filters }) }))
}

export async function allocateHours(projectId: number, input: AllocationInput): Promise<HourBag> {
  return dataOf(api.post<ApiEnvelope<HourBag>>(`/projects/${projectId}/allocations`, input))
}

/** RF-05.08: one click from the block. */
export async function requestExtension(projectId: number, message?: string): Promise<ExtensionRequest> {
  return dataOf(
    api.post<ApiEnvelope<ExtensionRequest>>(`/projects/${projectId}/extension-requests`, { message: message ?? null }),
  )
}

export async function fetchExtensionRequests(): Promise<ExtensionRequest[]> {
  return dataOf(api.get<ApiEnvelope<ExtensionRequest[]>>('/extension-requests'))
}

export async function approveExtension(requestId: number, input: AllocationInput): Promise<ExtensionRequest> {
  return dataOf(api.post<ApiEnvelope<ExtensionRequest>>(`/extension-requests/${requestId}/approve`, input))
}

export async function rejectExtension(requestId: number): Promise<ExtensionRequest> {
  return dataOf(api.post<ApiEnvelope<ExtensionRequest>>(`/extension-requests/${requestId}/reject`))
}
