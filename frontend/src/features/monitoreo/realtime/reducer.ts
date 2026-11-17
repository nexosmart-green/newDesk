// Aplica los eventos de Reverb al cache de TanStack Query. Funciones puras + un
// dispatcher que decide qué actualizar en sitio y qué invalidar.
import type { QueryClient } from '@tanstack/react-query'
import { keys } from '../api/useMonitoring'
import type { MetricsEvent, ServerDetail, ServerSummary, SnapshotEvent, StatusEvent } from '../api/types'

export type PanelEvent =
  | { type: 'server.status'; payload: StatusEvent }
  | { type: 'metrics'; payload: MetricsEvent }
  | { type: 'snapshot'; payload: SnapshotEvent }
  | { type: 'alert'; payload: { server_id: number } }
  | { type: 'report'; payload: ReportEvent }
  | { type: 'report.message'; payload: ReportEvent }

// Un reporte de cliente cambió o tiene un mensaje nuevo (D-049). Llega igual al equipo y al portal.
export interface ReportEvent { report_id: number; project_slug: string }

export function applyStatus(servers: ServerSummary[] | undefined, event: StatusEvent): ServerSummary[] | undefined {
  if (!servers) return servers
  return servers.map((server) =>
    server.id === event.server_id ? { ...server, status: event.status, last_seen_at: event.last_seen_at } : server,
  )
}

export function applyMetrics(servers: ServerSummary[] | undefined, event: MetricsEvent): ServerSummary[] | undefined {
  if (!servers) return servers
  return servers.map((server) =>
    server.id === event.server_id
      ? { ...server, metrics: event.metrics, last_seen_at: event.metrics.collected_at, status: server.status === 'unknown' ? 'online' : server.status }
      : server,
  )
}

export function applyToDetail(detail: ServerDetail | undefined, event: PanelEvent): ServerDetail | undefined {
  if (!detail || event.type === 'snapshot' || event.type === 'alert' || event.type === 'report' || event.type === 'report.message') return detail
  if (detail.server.id !== event.payload.server_id) return detail
  if (event.type === 'server.status') {
    const [server] = applyStatus([detail.server], event.payload) ?? [detail.server]
    return server ? { ...detail, server } : detail
  }
  const [server] = applyMetrics([detail.server], event.payload) ?? [detail.server]
  const full = event.payload.full
  const previous = detail.sections.metrics
  const sections = full
    ? {
        ...detail.sections,
        metrics: {
          collected_at: event.payload.metrics.collected_at,
          fetched_at: new Date().toISOString(),
          agent_version: previous?.agent_version ?? detail.server.agent_version ?? '',
          data: full,
          warnings: null,
          error: null,
        },
      }
    : detail.sections
  return { ...detail, server: server ?? detail.server, sections }
}

// Valida el nombre (broadcastAs) y el payload que entrega Echo. Devuelve null si no es un evento conocido.
export function parseEvent(type: string, payload: unknown): PanelEvent | null {
  if (typeof payload !== 'object' || payload === null) return null
  if (type === 'report' || type === 'report.message') return 'report_id' in payload ? { type, payload: payload as ReportEvent } : null
  if (!('server_id' in payload)) return null
  switch (type) {
    case 'server.status':
      return { type, payload: payload as StatusEvent }
    case 'metrics':
      return { type, payload: payload as MetricsEvent }
    case 'snapshot':
      return { type, payload: payload as SnapshotEvent }
    case 'alert':
      return { type, payload: payload as { server_id: number } }
    default:
      return null
  }
}

export function dispatchEvent(client: QueryClient, event: PanelEvent): void {
  if (event.type === 'report' || event.type === 'report.message') {
    // Las listas, ese detalle, el roadmap y el número del menú.
    void client.invalidateQueries({ queryKey: keys.allReports })
    return
  }
  const serverId = event.payload.server_id
  switch (event.type) {
    case 'server.status':
      client.setQueryData<ServerSummary[]>(keys.servers, (old) => applyStatus(old, event.payload))
      client.setQueryData<ServerDetail>(keys.server(serverId), (old) => applyToDetail(old, event))
      break
    case 'metrics':
      client.setQueryData<ServerSummary[]>(keys.servers, (old) => applyMetrics(old, event.payload))
      client.setQueryData<ServerDetail>(keys.server(serverId), (old) => applyToDetail(old, event))
      break
    case 'snapshot':
      void client.invalidateQueries({ queryKey: keys.server(serverId), exact: true })
      if (event.payload.section === 'sites') void client.invalidateQueries({ queryKey: keys.sites(serverId) })
      break
    case 'alert':
      void client.invalidateQueries({ queryKey: keys.allAlerts })
      void client.invalidateQueries({ queryKey: keys.servers })
      break
  }
}
