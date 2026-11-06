export interface Company {
  id: number
  name: string
  currency: string
  timezone: string
}

export interface TwoFactorStatus {
  enabled: boolean
  /** Admins must have it (RF-01.02); until enabled, every other endpoint answers 403. */
  required: boolean
}

export interface CurrentUser {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  locale: string
  timezone: string
  role: { id: number; name: string }
  permissions: string[]
  two_factor: TwoFactorStatus
  company: Company
}

export type LoginResult = { status: 'authenticated'; user: CurrentUser } | { status: 'two_factor_required' }

export type TwoFactorChallengeInput = { code: string } | { recovery_code: string }

export interface ResetPasswordInput {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface InvitationSummary {
  first_name: string
  email: string
  company: string
}

export interface NewPasswordInput {
  password: string
  password_confirmation: string
}

export function mustSetUpTwoFactor(user: CurrentUser): boolean {
  return user.two_factor.required && !user.two_factor.enabled
}

export const emptyNewPassword: NewPasswordInput = { password: '', password_confirmation: '' }
