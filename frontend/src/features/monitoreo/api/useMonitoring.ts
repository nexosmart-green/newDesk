import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Section } from './contract'
import { monitoringApi as api } from './monitoringApi'
import type {
  Alert, AlertState, IndexedProject, MetricsRange, MetricsSeries, ServerDetail, ServerInput, ServerSummary, SiteCheck, TestResult,
} from './types'

// Todo bajo 'monitoring' para no chocar con las keys de los demás módulos de newDesk.
export const keys = {
  servers: ['monitoring', 'servers'] as const,
  server: (id: number) => ['monitoring', 'server', id] as const,
  metrics: (id: number, range: MetricsRange) => ['monitoring', 'server', id, 'metrics', range] as const,
  sites: (id: number) => ['monitoring', 'server', id, 'sites'] as const,
  allAlerts: ['monitoring', 'alerts'] as const,
  alerts: (state: AlertState, page: number, serverId?: number) => ['monitoring', 'alerts', state, page, serverId ?? null] as const,
  inventory: ['monitoring', 'inventory', 'projects'] as const,
}

export function useServers() {
  return useQuery({ queryKey: keys.servers, queryFn: () => api.get<ServerSummary[]>('servers'), refetchInterval: 60_000 })
}

// Proyectos de todos los servers. Se pide recién cuando alguien busca y cambia poco
// (el agente los relee cada 10 min).
export function useProjectIndex(enabled = true) {
  return useQuery({ queryKey: keys.inventory, queryFn: () => api.get<IndexedProject[]>('inventory/projects'), staleTime: 5 * 60_000, enabled })
}

export function useServer(id: number) {
  return useQuery({ queryKey: keys.server(id), queryFn: () => api.get<ServerDetail>(`servers/${id}`) })
}

export function useMetricsSeries(id: number, range: MetricsRange) {
  return useQuery({
    queryKey: keys.metrics(id, range),
    queryFn: () => api.get<MetricsSeries>(`servers/${id}/metrics?range=${range}`),
    placeholderData: keepPreviousData,
    refetchInterval: range === '1h' ? 60_000 : 300_000,
  })
}

export function useSiteChecks(id: number) {
  return useQuery({ queryKey: keys.sites(id), queryFn: () => api.get<SiteCheck[]>(`servers/${id}/sites`) })
}

export function useAlerts(state: AlertState, page: number, serverId?: number) {
  const params = new URLSearchParams({ state, page: String(page) })
  if (serverId) params.set('server_id', String(serverId))
  return useQuery({
    queryKey: keys.alerts(state, page, serverId),
    queryFn: () => api.getEnvelope<Alert[]>(`alerts?${params}`),
    placeholderData: keepPreviousData,
  })
}

export function useRefresh(id: number) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (section?: Section) => api.post<null>(`servers/${id}/refresh`, section ? { section } : {}),
    onSuccess: () => {
      // El backend responde 202; el dato nuevo llega por Reverb (evento snapshot). Refetch de respaldo.
      window.setTimeout(() => void client.invalidateQueries({ queryKey: keys.server(id) }), 4000)
    },
  })
}

export function useServerMutations() {
  const client = useQueryClient()
  const invalidate = () => client.invalidateQueries({ queryKey: keys.servers })
  return {
    create: useMutation({ mutationFn: (input: ServerInput) => api.post<ServerSummary>('servers', input), onSuccess: invalidate }),
    update: useMutation({
      mutationFn: ({ id, input }: { id: number; input: ServerInput }) => api.put<ServerSummary>(`servers/${id}`, input),
      onSuccess: invalidate,
    }),
    remove: useMutation({ mutationFn: (id: number) => api.del<null>(`servers/${id}`), onSuccess: invalidate }),
    test: useMutation({ mutationFn: (id: number) => api.post<TestResult>(`servers/${id}/test`) }),
  }
}
