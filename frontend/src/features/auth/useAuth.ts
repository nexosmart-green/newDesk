import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchCurrentUser, login, logout, type LoginInput } from './authApi'

export const currentUserKey = ['me'] as const

export function useCurrentUser() {
  return useQuery({ queryKey: currentUserKey, queryFn: fetchCurrentUser, staleTime: 60_000 })
}

/** UI hint only — backend policies are the real authorization (RNF-05). */
export function useCan(permission: string): boolean {
  const { data } = useCurrentUser()
  return data?.permissions.includes(permission) ?? false
}

export function useLogin() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: LoginInput) => login(input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: currentUserKey }),
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: logout,
    onSettled: () => queryClient.setQueryData(currentUserKey, null),
  })
}
