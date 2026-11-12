import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { currentUserKey, rolesKey, usersKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import { changeUserRole, fetchUsers, inviteUser, resendInvitation } from './usersApi'
import type { Paginated } from '@/lib/api'
import type { InviteUserInput, UserFilters, UserSummary } from './types'

export function useUsers(filters: UserFilters) {
  return useQuery({
    queryKey: [...usersKey, filters],
    queryFn: () => fetchUsers(filters),
    placeholderData: keepPreviousData,
  })
}



export function useInviteUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: InviteUserInput) => inviteUser(input),
    // The dialog closes onto the list with the invitation in it; role counts follow behind.
    onSuccess: () => refreshQueries(queryClient, { now: [usersKey], later: [rolesKey] }),
  })
}

export function useChangeUserRole() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ userId, roleId }: { userId: number; roleId: number }) => changeUserRole(userId, roleId),
    // The answer is the updated row: patched in every cached page. Role counts and my own permissions follow behind.
    onSuccess: (user) => {
      queryClient.setQueriesData<Paginated<UserSummary>>({ queryKey: usersKey }, (page) =>
        page && { ...page, data: page.data.map((row) => (row.id === user.id ? user : row)) },
      )
      return refreshQueries(queryClient, { later: [rolesKey, currentUserKey] })
    },
  })
}

export function useResendInvitation() {
  return useMutation({ mutationFn: (userId: number) => resendInvitation(userId) })
}
