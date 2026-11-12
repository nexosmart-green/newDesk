import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { currentUserKey, permissionsKey, rolesKey, usersKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import { createRole, deleteRole, fetchPermissions, fetchRoles, updateRole } from './rolesApi'
import type { SaveRoleInput } from './types'

export function useRoles() {
  return useQuery({ queryKey: rolesKey, queryFn: fetchRoles })
}

export function usePermissionCatalog() {
  return useQuery({ queryKey: permissionsKey, queryFn: fetchPermissions, staleTime: Infinity })
}

function useInvalidateRoles() {
  const queryClient = useQueryClient()
  // The roles tab waits for its list; my own permissions and the user list (role names) follow behind.
  return () => refreshQueries(queryClient, { now: [rolesKey], later: [currentUserKey, usersKey] })
}

export function useSaveRole(roleId: number | null) {
  const invalidateRoles = useInvalidateRoles()
  return useMutation({
    meta: { successMessage: roleId === null ? 'Rol creado.' : 'Rol guardado.' },
    mutationFn: (input: SaveRoleInput) => (roleId === null ? createRole(input) : updateRole(roleId, input)),
    onSuccess: invalidateRoles,
  })
}

export function useDeleteRole() {
  const invalidateRoles = useInvalidateRoles()
  return useMutation({
    mutationFn: (roleId: number) => deleteRole(roleId),
    onSuccess: (message) => {
      toast.success(message ?? 'Rol borrado.')
      return invalidateRoles()
    },
  })
}
