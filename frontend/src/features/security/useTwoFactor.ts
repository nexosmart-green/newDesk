import { useMutation, useQueryClient } from '@tanstack/react-query'
import { currentUserKey } from '@/features/auth/useAuth'
import { confirmTwoFactor, disableTwoFactor, startTwoFactorSetup } from './securityApi'

export function useStartTwoFactorSetup() {
  return useMutation({ mutationFn: () => startTwoFactorSetup() })
}

/**
 * Does NOT refresh /me on success: the recovery codes must stay on screen until the user
 * acknowledges them (see `useRefreshCurrentUser`).
 */
export function useConfirmTwoFactor() {
  return useMutation({ mutationFn: (code: string) => confirmTwoFactor(code) })
}

export function useDisableTwoFactor() {
  const refreshCurrentUser = useRefreshCurrentUser()
  return useMutation({ mutationFn: (password: string) => disableTwoFactor(password), onSuccess: refreshCurrentUser })
}

export function useRefreshCurrentUser() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: currentUserKey })
}
