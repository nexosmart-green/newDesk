import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createCatalogItem, deleteCatalogItem, fetchCatalog, renameCatalogItem } from './catalogsApi'
import { toast } from '@/components/ui/toast/toastStore'
import { catalogKey, peopleKey, profileKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { CatalogType } from './types'

export function useCatalog(type: CatalogType) {
  return useQuery({ queryKey: catalogKey(type), queryFn: () => fetchCatalog(type), staleTime: 5 * 60_000 })
}

function useInvalidateCatalog(type: CatalogType) {
  const queryClient = useQueryClient()
  // The settings list waits; profiles and directory cards embed catalog names and follow behind.
  return () => refreshQueries(queryClient, { now: [catalogKey(type)], later: [peopleKey, profileKey] })
}

export function useCreateCatalogItem(type: CatalogType) {
  const invalidate = useInvalidateCatalog(type)
  return useMutation({ mutationFn: (name: string) => createCatalogItem(type, name), onSuccess: invalidate, meta: { successMessage: 'Agregado.' } })
}

export function useRenameCatalogItem(type: CatalogType) {
  const invalidate = useInvalidateCatalog(type)
  return useMutation({
    mutationFn: ({ id, name }: { id: number; name: string }) => renameCatalogItem(type, id, name),
    onSuccess: invalidate,
    meta: { successMessage: 'Actualizado.' },
  })
}

export function useDeleteCatalogItem(type: CatalogType) {
  const invalidate = useInvalidateCatalog(type)
  return useMutation({
    mutationFn: (id: number) => deleteCatalogItem(type, id),
    onSuccess: (message) => {
      toast.success(message ?? 'Borrado.')
      return invalidate()
    },
  })
}
