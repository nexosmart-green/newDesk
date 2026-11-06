import { api, ensureCsrfCookie, toApiError, type ApiEnvelope } from '@/lib/api'
import type { CurrentUser } from './types'

export async function fetchCurrentUser(): Promise<CurrentUser | null> {
  try {
    const response = await api.get<ApiEnvelope<CurrentUser>>('/me')
    return response.data.data
  } catch (error) {
    const apiError = toApiError(error)
    if (apiError.status === 401) return null
    throw apiError
  }
}

export interface LoginInput {
  email: string
  password: string
}

export async function login(input: LoginInput): Promise<void> {
  await ensureCsrfCookie()
  try {
    await api.post('/auth/login', input)
  } catch (error) {
    throw toApiError(error)
  }
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}
