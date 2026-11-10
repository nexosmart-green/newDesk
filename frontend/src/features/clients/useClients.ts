import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { changeClientStatus, createClient, deleteClient, fetchClient, fetchClients, updateClient } from './clientsApi'
import { toast } from '@/components/ui/toast/toastStore'
import type { ClientFilters, ClientStatus, SaveClientInput } from './types'

export const clientsKey = ['clients'] as const
const clientKey = (id: number) => [...clientsKey, 'detail', id] as const

export function useClients(filters: ClientFilters, enabled = true) {
  return useQuery({
    queryKey: [...clientsKey, 'list', filters],
    queryFn: () => fetchClients(filters),
    placeholderData: keepPreviousData,
    enabled,
  })
}

/** CA-02.01: only active clients are offered for new projects. */
export function useActiveClients(enabled = true) {
  return useClients({ status: 'active', search: '', page: 1 }, enabled)
}

export function useClient(id: number) {
  return useQuery({ queryKey: clientKey(id), queryFn: () => fetchClient(id) })
}

function useInvalidateClients() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: clientsKey }),
      // Project rows show the client name.
      queryClient.invalidateQueries({ queryKey: ['projects'] }),
    ])
}

export function useSaveClient(clientId: number | null) {
  const invalidate = useInvalidateClients()
  return useMutation({
    mutationFn: (input: SaveClientInput) => (clientId === null ? createClient(input) : updateClient(clientId, input)),
    onSuccess: invalidate,
    meta: { successMessage: clientId === null ? 'Cliente creado.' : 'Cliente actualizado.' },
  })
}

export function useChangeClientStatus(clientId: number) {
  const invalidate = useInvalidateClients()
  return useMutation({
    mutationFn: (status: ClientStatus) => changeClientStatus(clientId, status),
    onSuccess: (client) => {
      toast.success(client.status === 'active' ? 'Cliente activado.' : 'Cliente inactivado.')
      return invalidate()
    },
  })
}

export function useDeleteClient(clientId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => deleteClient(clientId),
    onSuccess: (message) => {
      toast.success(message ?? 'Cliente borrado.')
      queryClient.removeQueries({ queryKey: clientKey(clientId) })
      return queryClient.invalidateQueries({ queryKey: [...clientsKey, 'list'] })
    },
  })
}
