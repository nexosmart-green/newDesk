import { api, dataOf, envelopeOf, type ApiEnvelope } from '@/lib/api'
import type { TwoFactorSetup } from './types'

/** Step 1: the backend stores an unconfirmed secret; 2FA is not active yet. */
export async function startTwoFactorSetup(): Promise<TwoFactorSetup> {
  return dataOf(api.post<ApiEnvelope<TwoFactorSetup>>('/me/two-factor'))
}

/** Step 2: returns the recovery codes. They are shown only once. */
export async function confirmTwoFactor(code: string): Promise<string[]> {
  const data = await dataOf(api.post<ApiEnvelope<{ recovery_codes: string[] }>>('/me/two-factor/confirm', { code }))
  return data.recovery_codes
}

export async function disableTwoFactor(password: string): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>('/me/two-factor', { data: { password } }))).message
}
