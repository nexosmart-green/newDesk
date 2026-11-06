import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, toApiError, type ApiEnvelope } from '@/lib/api'

// Los tokens personales para el MCP (RF-12.02): son de newDesk, viven en /api/v1/me/tokens (no en /monitoring).
export interface PersonalToken { id: number; name: string; created_at: string; last_used_at: string | null; expires_at: string | null }
export interface PersonalTokenCreated extends PersonalToken { token: string }
export interface NewTokenInput { name: string; expires_in_days: number | null }

const tokensKey = ['me', 'tokens'] as const

async function data<T>(send: Promise<{ data: ApiEnvelope<T> }>): Promise<T> {
  try {
    return (await send).data.data
  } catch (error) {
    throw toApiError(error)
  }
}

export function useTokens() {
  return useQuery({ queryKey: tokensKey, queryFn: () => data<PersonalToken[]>(api.get('/me/tokens')) })
}

export function useTokenMutations() {
  const client = useQueryClient()
  const invalidate = () => client.invalidateQueries({ queryKey: tokensKey })
  return {
    create: useMutation({ mutationFn: (input: NewTokenInput) => data<PersonalTokenCreated>(api.post('/me/tokens', input)), onSuccess: invalidate }),
    revoke: useMutation({ mutationFn: (id: number) => data<null>(api.delete(`/me/tokens/${id}`)), onSuccess: invalidate }),
  }
}
