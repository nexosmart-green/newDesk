import { api, dataOf, envelopeOf, type ApiEnvelope } from '@/lib/api'
import type { PermissionOption, Role, SaveRoleInput } from './types'

export async function fetchPermissions(): Promise<PermissionOption[]> {
  return dataOf(api.get<ApiEnvelope<PermissionOption[]>>('/permissions'))
}

export async function fetchRoles(): Promise<Role[]> {
  return dataOf(api.get<ApiEnvelope<Role[]>>('/roles'))
}

export async function createRole(input: SaveRoleInput): Promise<Role> {
  return dataOf(api.post<ApiEnvelope<Role>>('/roles', input))
}

export async function updateRole(id: number, input: SaveRoleInput): Promise<Role> {
  return dataOf(api.put<ApiEnvelope<Role>>(`/roles/${id}`, input))
}

export async function deleteRole(id: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/roles/${id}`))).message
}
