import { api, dataOf, ensureCsrfCookie, envelopeOf, toApiError, type ApiEnvelope } from '@/lib/api'
import type {
  CurrentUser,
  InvitationSummary,
  LoginResult,
  NewPasswordInput,
  ResetPasswordInput,
  TwoFactorChallengeInput,
} from './types'

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
  remember?: boolean
}

function isTwoFactorPending(data: unknown): boolean {
  return typeof data === 'object' && data !== null && 'two_factor_required' in data && data.two_factor_required === true
}

export async function login(input: LoginInput): Promise<LoginResult> {
  await ensureCsrfCookie()
  const data = await dataOf(api.post<ApiEnvelope<CurrentUser | { two_factor_required: true }>>('/auth/login', input))
  if (isTwoFactorPending(data)) return { status: 'two_factor_required' }
  return { status: 'authenticated', user: data as CurrentUser }
}

export async function submitTwoFactorChallenge(input: TwoFactorChallengeInput): Promise<CurrentUser> {
  return dataOf(api.post<ApiEnvelope<CurrentUser>>('/auth/two-factor-challenge', input))
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}

/** Returns the backend's generic message (it never reveals whether the e-mail exists). */
export async function requestPasswordReset(email: string): Promise<string | null> {
  await ensureCsrfCookie()
  return (await envelopeOf(api.post<ApiEnvelope<null>>('/auth/forgot-password', { email }))).message
}

export async function resetPassword(input: ResetPasswordInput): Promise<string | null> {
  await ensureCsrfCookie()
  return (await envelopeOf(api.post<ApiEnvelope<null>>('/auth/reset-password', input))).message
}

export async function fetchInvitation(token: string): Promise<InvitationSummary> {
  return dataOf(api.get<ApiEnvelope<InvitationSummary>>(`/invitations/${encodeURIComponent(token)}`))
}

export async function acceptInvitation(token: string, input: NewPasswordInput): Promise<string | null> {
  await ensureCsrfCookie()
  const path = `/invitations/${encodeURIComponent(token)}/accept`
  return (await envelopeOf(api.post<ApiEnvelope<{ email: string }>>(path, input))).message
}
