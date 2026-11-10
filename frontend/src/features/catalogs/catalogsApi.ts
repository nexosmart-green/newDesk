import { api, dataOf, envelopeOf, type ApiEnvelope } from '@/lib/api'
import type { CatalogItem, CatalogType } from './types'

export async function fetchCatalog(type: CatalogType): Promise<CatalogItem[]> {
  return dataOf(api.get<ApiEnvelope<CatalogItem[]>>(`/catalogs/${type}`))
}

export async function createCatalogItem(type: CatalogType, name: string): Promise<CatalogItem> {
  return dataOf(api.post<ApiEnvelope<CatalogItem>>(`/catalogs/${type}`, { name }))
}

export async function renameCatalogItem(type: CatalogType, id: number, name: string): Promise<CatalogItem> {
  return dataOf(api.put<ApiEnvelope<CatalogItem>>(`/catalogs/${type}/${id}`, { name }))
}

export async function deleteCatalogItem(type: CatalogType, id: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/catalogs/${type}/${id}`))).message
}
