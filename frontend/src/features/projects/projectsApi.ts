import type { PersonSummary } from '@/features/people/types'
import { api, dataOf, envelopeOf, pageOf, queryParams, type ApiEnvelope, type Paginated } from '@/lib/api'
import type {
  ActivityEntry,
  CreateProjectInput,
  ProjectDetail,
  ProjectFilters,
  ProjectStatus,
  ProjectSummary,
  SaveProjectInput,
} from './types'

export async function fetchProjects(filters: ProjectFilters): Promise<Paginated<ProjectSummary>> {
  const params = queryParams({ ...filters })
  return pageOf(api.get<Paginated<ProjectSummary>>('/projects', { params }))
}

export async function fetchProject(id: number): Promise<ProjectDetail> {
  return dataOf(api.get<ApiEnvelope<ProjectDetail>>(`/projects/${id}`))
}

export async function createProject(input: CreateProjectInput): Promise<ProjectDetail> {
  return dataOf(api.post<ApiEnvelope<ProjectDetail>>('/projects', input))
}

export async function updateProject(id: number, input: Partial<SaveProjectInput>): Promise<ProjectDetail> {
  return dataOf(api.put<ApiEnvelope<ProjectDetail>>(`/projects/${id}`, input))
}

export async function changeProjectStatus(id: number, status: ProjectStatus): Promise<ProjectDetail> {
  return dataOf(api.put<ApiEnvelope<ProjectDetail>>(`/projects/${id}/status`, { status }))
}

export async function fetchProjectActivity(id: number, page: number): Promise<Paginated<ActivityEntry>> {
  return pageOf(api.get<Paginated<ActivityEntry>>(`/projects/${id}/activity`, { params: { page } }))
}

export async function addProjectMember(id: number, userId: number): Promise<PersonSummary> {
  return dataOf(api.post<ApiEnvelope<PersonSummary>>(`/projects/${id}/members`, { user_id: userId }))
}

export async function removeProjectMember(id: number, userId: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/projects/${id}/members/${userId}`))).message
}
