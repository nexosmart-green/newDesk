import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createAiToken, fetchCompanyAiTokens, fetchMyAiTokens, revokeAiToken } from './aiConnectionsApi'
import type { CreateTokenInput } from './types'

const aiTokensKey = ['ai-tokens'] as const

export function useMyAiTokens() {
  return useQuery({ queryKey: [...aiTokensKey, 'mine'], queryFn: fetchMyAiTokens })
}

export function useCompanyAiTokens() {
  return useQuery({ queryKey: [...aiTokensKey, 'company'], queryFn: fetchCompanyAiTokens })
}

function useRefreshTokens() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: aiTokensKey })
}

export function useCreateAiToken() {
  const refresh = useRefreshTokens()
  // gcTime 0: the plain-text token must not linger in the mutation cache after its dialog closes.
  return useMutation({ mutationFn: (input: CreateTokenInput) => createAiToken(input), onSuccess: refresh, gcTime: 0 })
}

export function useRevokeAiToken() {
  const refresh = useRefreshTokens()
  return useMutation({
    mutationFn: (id: number) => revokeAiToken(id),
    onSuccess: refresh,
    meta: { successMessage: 'Token revocado.' },
  })
}
