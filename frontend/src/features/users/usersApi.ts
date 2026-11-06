import { api, dataOf, envelopeOf, toApiError, type ApiEnvelope, type Paginated } from '@/lib/api'
import type { InviteUserInput, UserFilters, UserSummary } from './types'

export async function fetchUsers(filters: UserFilters): Promise<Paginated<UserSummary>> {
  const params = {
    page: filters.page,
    ...(filters.status && { status: filters.status }),
    ...(filters.search.trim() && { search: filters.search.trim() }),
  }
  try {
    const response = await api.get<Paginated<UserSummary>>('/users', { params })
    return { data: response.data.data, meta: response.data.meta }
  } catch (error) {
    throw toApiError(error)
  }
}

export async function inviteUser(input: InviteUserInput): Promise<UserSummary> {
  return dataOf(api.post<ApiEnvelope<UserSummary>>('/users', input))
}

export async function changeUserRole(userId: number, roleId: number): Promise<UserSummary> {
  return dataOf(api.put<ApiEnvelope<UserSummary>>(`/users/${userId}/role`, { role_id: roleId }))
}

export async function resendInvitation(userId: number): Promise<string | null> {
  return (await envelopeOf(api.post<ApiEnvelope<null>>(`/users/${userId}/invitation`))).message
}
