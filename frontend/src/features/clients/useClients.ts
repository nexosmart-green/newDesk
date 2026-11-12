import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { changeClientStatus, createClient, deleteClient, fetchClient, fetchClients, updateClient } from './clientsApi'
import { toast } from '@/components/ui/toast/toastStore'
import { clientKey, clientListsKey, projectListsKey, projectsKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { Client, ClientFilters, ClientStatus, SaveClientInput } from './types'

export function useClients(filters: ClientFilters, enabled = true) {
  return useQuery({
    queryKey: [...clientListsKey, filters],
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

/** The saved client goes straight to its file (keeping its projects); lists and the projects that show its name follow behind. */
function useStoreClient() {
  const queryClient = useQueryClient()
  return (client: Client) => {
    queryClient.setQueryData<Client>(clientKey(client.id), (current) => ({ ...current, ...client }))
    return refreshQueries(queryClient, { later: [clientListsKey, projectListsKey, [...projectsKey, 'detail']] })
  }
}

export function useSaveClient(clientId: number | null) {
  const store = useStoreClient()
  return useMutation({
    mutationFn: (input: SaveClientInput) => (clientId === null ? createClient(input) : updateClient(clientId, input)),
    onSuccess: store,
    meta: { successMessage: clientId === null ? 'Cliente creado.' : 'Cliente actualizado.' },
  })
}

export function useChangeClientStatus(clientId: number) {
  const store = useStoreClient()
  return useMutation({
    mutationFn: (status: ClientStatus) => changeClientStatus(clientId, status),
    onSuccess: (client) => {
      toast.success(client.status === 'active' ? 'Cliente activado.' : 'Cliente inactivado.')
      return store(client)
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
      return refreshQueries(queryClient, { later: [clientListsKey] })
    },
  })
}
