import { api, dataOf, pageOf, queryParams, type ApiEnvelope, type Paginated } from '@/lib/api'
import type {
  AddRateInput,
  DirectoryFilters,
  HourlyRate,
  PersonProfile,
  PersonSummary,
  UpdateEmployeeInput,
} from './types'

export function directoryQueryParams(filters: DirectoryFilters): Record<string, string | number> {
  return queryParams({
    page: filters.page,
    search: filters.search,
    area_id: filters.area_id,
    skill_id: filters.skill_id,
    // A minimum level only means something together with a skill.
    min_level: filters.skill_id ? filters.min_level : null,
    include_inactive: filters.include_inactive,
  })
}

export async function fetchPeople(filters: DirectoryFilters): Promise<Paginated<PersonSummary>> {
  return pageOf(api.get<Paginated<PersonSummary>>('/people', { params: directoryQueryParams(filters) }))
}

export async function fetchPerson(id: number): Promise<PersonProfile> {
  return dataOf(api.get<ApiEnvelope<PersonProfile>>(`/people/${id}`))
}

export async function updatePerson(id: number, input: UpdateEmployeeInput): Promise<PersonProfile> {
  return dataOf(api.put<ApiEnvelope<PersonProfile>>(`/people/${id}`, input))
}

export async function deactivatePerson(id: number): Promise<PersonSummary> {
  return dataOf(api.post<ApiEnvelope<PersonSummary>>(`/people/${id}/deactivate`))
}

export async function reactivatePerson(id: number): Promise<PersonSummary> {
  return dataOf(api.post<ApiEnvelope<PersonSummary>>(`/people/${id}/reactivate`))
}

export async function fetchRates(personId: number): Promise<HourlyRate[]> {
  return dataOf(api.get<ApiEnvelope<HourlyRate[]>>(`/people/${personId}/rates`))
}

export async function addRate(personId: number, input: AddRateInput): Promise<{ id: number }> {
  return dataOf(api.post<ApiEnvelope<{ id: number }>>(`/people/${personId}/rates`, input))
}
