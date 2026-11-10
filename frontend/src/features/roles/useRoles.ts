import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { currentUserKey } from '@/features/auth/useAuth'
import { createRole, deleteRole, fetchPermissions, fetchRoles, updateRole } from './rolesApi'
import type { SaveRoleInput } from './types'

export const rolesKey = ['roles'] as const
export const permissionsKey = ['permissions'] as const

export function useRoles() {
  return useQuery({ queryKey: rolesKey, queryFn: fetchRoles })
}

export function usePermissionCatalog() {
  return useQuery({ queryKey: permissionsKey, queryFn: fetchPermissions, staleTime: Infinity })
}

function useInvalidateRoles() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: rolesKey }),
      // Editing my own role changes my permissions; the user list shows role names.
      queryClient.invalidateQueries({ queryKey: currentUserKey }),
      queryClient.invalidateQueries({ queryKey: ['users'] }),
    ])
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
