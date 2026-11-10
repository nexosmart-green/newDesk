import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { CompanyAiToken, CreateTokenInput, IssuedToken, MyAiTokens } from './types'

export async function fetchMyAiTokens(): Promise<MyAiTokens> {
  return dataOf(api.get<ApiEnvelope<MyAiTokens>>('/me/ai-tokens'))
}

export async function createAiToken(input: CreateTokenInput): Promise<IssuedToken> {
  return dataOf(api.post<ApiEnvelope<IssuedToken>>('/me/ai-tokens', input))
}

export async function revokeAiToken(id: number): Promise<void> {
  await dataOf(api.delete<ApiEnvelope<null>>(`/ai-tokens/${id}`))
}

/** RF-12.05: every token in the company (`mcp.manage_tokens`). */
export async function fetchCompanyAiTokens(): Promise<CompanyAiToken[]> {
  return dataOf(api.get<ApiEnvelope<CompanyAiToken[]>>('/ai-tokens'))
}
