import { useMutation, useQueryClient } from '@tanstack/react-query'
import { currentUserKey } from '@/lib/queryKeys'
import { confirmTwoFactor, disableTwoFactor, startTwoFactorSetup } from './securityApi'

export function useStartTwoFactorSetup() {
  return useMutation({ mutationFn: () => startTwoFactorSetup(), meta: { silentError: true } })
}

/**
 * Does NOT refresh /me on success: the recovery codes must stay on screen until the user
 * acknowledges them (see `useRefreshCurrentUser`).
 */
export function useConfirmTwoFactor() {
  return useMutation({ mutationFn: (code: string) => confirmTwoFactor(code), meta: { silentError: true } })
}

export function useDisableTwoFactor() {
  const refreshCurrentUser = useRefreshCurrentUser()
  return useMutation({
    mutationFn: (password: string) => disableTwoFactor(password),
    // The form explains its own errors inline (wrong password…); the outcome is a toast.
    meta: { silentError: true, successMessage: 'Verificación en dos pasos desactivada.' },
    onSuccess: refreshCurrentUser,
  })
}

export function useRefreshCurrentUser() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: currentUserKey })
}
