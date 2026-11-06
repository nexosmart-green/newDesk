import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { currentUserKey } from '@/features/auth/useAuth'
import { rolesKey } from '@/features/roles/useRoles'
import { changeUserRole, fetchUsers, inviteUser, resendInvitation } from './usersApi'
import type { InviteUserInput, UserFilters } from './types'

export const usersKey = ['users'] as const

export function useUsers(filters: UserFilters) {
  return useQuery({
    queryKey: [...usersKey, filters],
    queryFn: () => fetchUsers(filters),
    placeholderData: keepPreviousData,
  })
}

function useInvalidateUsers() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: usersKey }),
      // Roles show how many users they have.
      queryClient.invalidateQueries({ queryKey: rolesKey }),
    ])
}

export function useInviteUser() {
  const invalidateUsers = useInvalidateUsers()
  return useMutation({ mutationFn: (input: InviteUserInput) => inviteUser(input), onSuccess: invalidateUsers })
}

export function useChangeUserRole() {
  const queryClient = useQueryClient()
  const invalidateUsers = useInvalidateUsers()
  return useMutation({
    mutationFn: ({ userId, roleId }: { userId: number; roleId: number }) => changeUserRole(userId, roleId),
    onSuccess: () =>
      Promise.all([invalidateUsers(), queryClient.invalidateQueries({ queryKey: currentUserKey })]),
  })
}

export function useResendInvitation() {
  return useMutation({ mutationFn: (userId: number) => resendInvitation(userId) })
}
