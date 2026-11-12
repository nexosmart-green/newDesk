import type { PersonProfile, PersonSummary, UpdateOwnProfileInput } from '@/features/people/types'
import { api, dataOf, type ApiEnvelope } from '@/lib/api'

/** `/me/*`: what each person edits about themself. The profile shape is the people directory's. */
export async function fetchMyProfile(): Promise<PersonProfile> {
  return dataOf(api.get<ApiEnvelope<PersonProfile>>('/me/profile'))
}

export async function updateMyProfile(input: UpdateOwnProfileInput): Promise<PersonProfile> {
  return dataOf(api.put<ApiEnvelope<PersonProfile>>('/me/profile', input))
}

export async function uploadMyAvatar(avatar: Blob, fileName: string): Promise<PersonProfile> {
  const body = new FormData()
  body.append('avatar', avatar, fileName)
  return dataOf(api.post<ApiEnvelope<PersonProfile>>('/me/avatar', body))
}

export async function saveMySkills(skills: { id: number; level: number }[]): Promise<PersonProfile> {
  return dataOf(api.put<ApiEnvelope<PersonProfile>>('/me/skills', { skills }))
}

export async function fetchMyTeam(): Promise<PersonSummary[]> {
  return dataOf(api.get<ApiEnvelope<PersonSummary[]>>('/me/team'))
}
