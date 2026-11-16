import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createAiToken, fetchCompanyAiTokens, fetchMyAiTokens, revokeAiToken } from './aiConnectionsApi'
import { aiTokensKey } from '@/lib/queryKeys'
import { toast } from '@/components/ui/toast/toastStore'
import type { AiToken, CreateTokenInput } from './types'

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
    mutationFn: (token: AiToken) => revokeAiToken(token.id),
    onSuccess: (_result, token) => {
      toast.success(`Token «${token.name}» revocado.`)
      return refresh()
    },
  })
}
