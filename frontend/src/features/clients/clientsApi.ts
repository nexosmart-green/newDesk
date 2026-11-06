import { api, dataOf, envelopeOf, pageOf, queryParams, type ApiEnvelope, type Paginated } from '@/lib/api'
import type { Client, ClientFilters, ClientStatus, SaveClientInput } from './types'

export async function fetchClients(filters: ClientFilters): Promise<Paginated<Client>> {
  const params = queryParams({ page: filters.page, status: filters.status, search: filters.search })
  return pageOf(api.get<Paginated<Client>>('/clients', { params }))
}

export async function fetchClient(id: number): Promise<Client> {
  return dataOf(api.get<ApiEnvelope<Client>>(`/clients/${id}`))
}

export async function createClient(input: SaveClientInput): Promise<Client> {
  return dataOf(api.post<ApiEnvelope<Client>>('/clients', input))
}

export async function updateClient(id: number, input: SaveClientInput): Promise<Client> {
  return dataOf(api.put<ApiEnvelope<Client>>(`/clients/${id}`, input))
}

export async function changeClientStatus(id: number, status: ClientStatus): Promise<Client> {
  return dataOf(api.put<ApiEnvelope<Client>>(`/clients/${id}/status`, { status }))
}

export async function deleteClient(id: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/clients/${id}`))).message
}
