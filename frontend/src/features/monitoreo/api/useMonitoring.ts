import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Section } from './contract'
import { monitoringApi as api } from './monitoringApi'
import type {
  Alert, AlertState, IndexedProject, MetricsRange, MetricsSeries, ReportStatus, ResponseRange, ServerDetail, ServerInput, ServerSummary, SiteCheck,
  TestResult,
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
  // Clientes, proyectos y reportes: el árbol 'portal-admin' de monitoreo, también bajo 'monitoring'.
  portalAdmin: ['monitoring', 'portal-admin'] as const,
  allProjects: ['monitoring', 'portal-admin', 'projects'] as const,
  clients: ['monitoring', 'portal-admin', 'clients'] as const,
  client: (id: number) => ['monitoring', 'portal-admin', 'clients', id] as const,
  adminProject: (id: number) => ['monitoring', 'portal-admin', 'projects', id] as const,
  preview: (id: number) => ['monitoring', 'portal-admin', 'projects', id, 'preview'] as const,
  incidents: (id: number) => ['monitoring', 'portal-admin', 'projects', id, 'incidents'] as const,
  suggestions: (id: number) => ['monitoring', 'portal-admin', 'projects', id, 'suggestions'] as const,
  vulnerabilities: (id: number) => ['monitoring', 'portal-admin', 'projects', id, 'vulnerabilities'] as const,
  targets: ['monitoring', 'portal-admin', 'targets'] as const,
  folders: ['monitoring', 'portal-admin', 'folders'] as const,
  // El portal del cliente (usePortal): ['portal', slug] es prefijo de sus reportes, como en monitoreo.
  portal: ['monitoring', 'portal'] as const,
  portalProject: (slug: string) => ['monitoring', 'portal', slug] as const,
  portalReports: (slug: string) => ['monitoring', 'portal', slug, 'reports'] as const,
  portalReport: (slug: string, id: number) => ['monitoring', 'portal', slug, 'reports', id] as const,
  // path = la base del proyecto (portal/projects/<slug> del cliente o portal-admin/projects/<id> en la vista previa del equipo).
  responseTimes: (path: string, range: ResponseRange) => ['monitoring', 'response-times', path, range] as const,
  allReports: ['monitoring', 'portal-admin', 'reports'] as const,
  reports: (status: ReportStatus | null, page: number) => ['monitoring', 'portal-admin', 'reports', status, page] as const,
  report: (id: number) => ['monitoring', 'portal-admin', 'reports', id] as const,
  reportsAttention: ['monitoring', 'portal-admin', 'reports', 'attention'] as const,
  // Bajo "reports": lo que refresca los reportes (estado, chat, Reverb) refresca también el roadmap.
  roadmap: ['monitoring', 'portal-admin', 'reports', 'roadmap'] as const,
  teamUsers: ['monitoring', 'users'] as const,
  discordChannels: ['monitoring', 'discord', 'channels'] as const,
  dailyReportStatus: ['monitoring', 'daily-report', 'status'] as const,
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
