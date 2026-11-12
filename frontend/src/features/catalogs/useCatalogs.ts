import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createCatalogItem, deleteCatalogItem, fetchCatalog, renameCatalogItem } from './catalogsApi'
import { toast } from '@/components/ui/toast/toastStore'
import { catalogKey, peopleKey, profileKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import { catalogItemDone, type CatalogItem, type CatalogType } from './types'

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
  return useMutation({
    mutationFn: (name: string) => createCatalogItem(type, name),
    onSuccess: (_item, name) => {
      toast.success(catalogItemDone(type, name, 'agregad'))
      return invalidate()
    },
  })
}

export function useRenameCatalogItem(type: CatalogType) {
  const invalidate = useInvalidateCatalog(type)
  return useMutation({
    mutationFn: ({ id, name }: { id: number; name: string }) => renameCatalogItem(type, id, name),
    onSuccess: (_item, { name }) => {
      toast.success(catalogItemDone(type, name, 'guardad'))
      return invalidate()
    },
  })
}

export function useDeleteCatalogItem(type: CatalogType) {
  const invalidate = useInvalidateCatalog(type)
  return useMutation({
    mutationFn: (item: CatalogItem) => deleteCatalogItem(type, item.id),
    // The backend says only "Borrado.": the name tells which one went.
    onSuccess: (_message, item) => {
      toast.success(catalogItemDone(type, item.name, 'borrad'))
      return invalidate()
    },
  })
}
