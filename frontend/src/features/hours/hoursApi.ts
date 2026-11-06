import type { TimeEntry } from '@/features/timer/types'
import { api, dataOf, pageOf, queryParams, type ApiEnvelope, type Paginated } from '@/lib/api'
import type { AllocationInput, ExtensionRequest, HourBag, ProjectEntryFilters, ProjectHours } from './types'

export async function fetchProjectHours(projectId: number): Promise<ProjectHours> {
  return dataOf(api.get<ApiEnvelope<ProjectHours>>(`/projects/${projectId}/hours`))
}

export async function fetchProjectEntries(projectId: number, filters: ProjectEntryFilters): Promise<Paginated<TimeEntry>> {
  return pageOf(api.get<Paginated<TimeEntry>>(`/projects/${projectId}/time-entries`, { params: queryParams({ ...filters }) }))
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
