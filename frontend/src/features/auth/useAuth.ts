import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  acceptInvitation,
  fetchCurrentUser,
  fetchInvitation,
  login,
  logout,
  requestPasswordReset,
  resetPassword,
  submitTwoFactorChallenge,
  type LoginInput,
} from './authApi'
import { browserTimeZone } from '@/lib/timezone'
import type { CurrentUser, NewPasswordInput, ResetPasswordInput, TwoFactorChallengeInput } from './types'

export const currentUserKey = ['me'] as const

export function useCurrentUser() {
  return useQuery({ queryKey: currentUserKey, queryFn: fetchCurrentUser, staleTime: 60_000 })
}

/** UI hint only — backend policies are the real authorization (RNF-05). */
export function useCan(permission: string): boolean {
  return useCanAny([permission])
}

export function useCanAny(permissions: readonly string[]): boolean {
  const { data } = useCurrentUser()
  return permissions.some((permission) => data?.permissions.includes(permission) ?? false)
}

/** The person's effective (profile, else company) timezone: the one the backend reads typed dates/times in. */
export function useProfileTimeZone(): string {
  const { data } = useCurrentUser()
  return data?.timezone ?? browserTimeZone()
}

// Auth forms explain their errors inline (no toast).
const silentError = { silentError: true } as const

function useStoreCurrentUser() {
  const queryClient = useQueryClient()
  return (user: CurrentUser) => queryClient.setQueryData(currentUserKey, user)
}

export function useLogin() {
  const storeCurrentUser = useStoreCurrentUser()
  return useMutation({
    meta: silentError,
    mutationFn: (input: LoginInput) => login(input),
    onSuccess: (result) => {
      if (result.status === 'authenticated') storeCurrentUser(result.user)
    },
  })
}

export function useTwoFactorChallenge() {
  const storeCurrentUser = useStoreCurrentUser()
  return useMutation({
    meta: silentError,
    mutationFn: (input: TwoFactorChallengeInput) => submitTwoFactorChallenge(input),
    onSuccess: storeCurrentUser,
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    meta: silentError,
    mutationFn: logout,
    onSettled: () => {
      queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== currentUserKey[0] })
      queryClient.setQueryData(currentUserKey, null)
    },
  })
}

export function useRequestPasswordReset() {
  return useMutation({ meta: silentError, mutationFn: (email: string) => requestPasswordReset(email) })
}

export function useResetPassword() {
  return useMutation({ meta: silentError, mutationFn: (input: ResetPasswordInput) => resetPassword(input) })
}

export function useInvitation(token: string) {
  return useQuery({ queryKey: ['invitations', token], queryFn: () => fetchInvitation(token), staleTime: Infinity })
}

export function useAcceptInvitation(token: string) {
  return useMutation({ meta: silentError, mutationFn: (input: NewPasswordInput) => acceptInvitation(token, input) })
}
