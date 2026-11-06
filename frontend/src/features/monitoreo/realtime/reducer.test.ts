import { QueryClient } from '@tanstack/react-query'
import { keys } from '../api/useMonitoring'
import type { ServerDetail, ServerSummary } from '../api/types'
import { applyMetrics, applyStatus, applyToDetail, dispatchEvent, parseEvent } from './reducer'

const base: ServerSummary = {
  id: 1, name: 'reaquila', ip: '51.222.205.245', ssh_port: 1080, agent_port: 9100, enabled: true,
  status: 'online', last_seen_at: '2026-09-30T13:00:00Z', hostname: 'mail.reaquila.com', os: 'Ubuntu 24.10',
  metrics: null, active_alerts: 0, agent_version: 'v0.1.0',
}
const other: ServerSummary = { ...base, id: 2, name: 'otro' }
const summary = { cpu_percent: 12, memory_percent: 67, swap_percent: 66, disk_percent: 65, load1: 0.3, uptime_seconds: 100, collected_at: '2026-09-30T13:01:00Z' }

describe('reducer de eventos en tiempo real', () => {
  it('actualiza solo el server del evento de estado', () => {
    const out = applyStatus([base, other], { server_id: 1, status: 'down', last_seen_at: null })
    expect(out?.[0]?.status).toBe('down')
    expect(out?.[1]).toBe(other)
  })

  it('aplica métricas y actualiza last_seen_at', () => {
    const out = applyMetrics([base], { server_id: 1, metrics: summary })
    expect(out?.[0]?.metrics?.cpu_percent).toBe(12)
    expect(out?.[0]?.last_seen_at).toBe(summary.collected_at)
  })

  it('no toca un cache vacío', () => {
    expect(applyStatus(undefined, { server_id: 1, status: 'down', last_seen_at: null })).toBeUndefined()
  })

  it('reemplaza la sección metrics del detalle cuando llega el payload completo', () => {
    const detail: ServerDetail = { server: base, sections: {} }
    const out = applyToDetail(detail, {
      type: 'metrics',
      payload: {
        server_id: 1, metrics: summary,
        full: {
          cpu: { usage_percent: 12, cores: 4, load1: 0.3, load5: 0.3, load15: 0.4 },
          memory: { total_bytes: 1, used_bytes: 1, available_bytes: 0, used_percent: 100 },
          swap: { total_bytes: 0, used_bytes: 0, used_percent: 0 },
          disks: [], network: [], uptime_seconds: 1, boot_time: '2026-07-10T00:00:00Z', top_by_cpu: [], top_by_memory: [],
        },
      },
    })
    expect(out?.sections.metrics?.data?.cpu.cores).toBe(4)
    expect(out?.server.metrics?.cpu_percent).toBe(12)
  })

  it('ignora eventos de otro server en el detalle', () => {
    const detail: ServerDetail = { server: base, sections: {} }
    expect(applyToDetail(detail, { type: 'server.status', payload: { server_id: 2, status: 'down', last_seen_at: null } })).toBe(detail)
  })

  it('parsea eventos conocidos y descarta basura', () => {
    expect(parseEvent('metrics', { server_id: 1, metrics: summary })?.type).toBe('metrics')
    expect(parseEvent('metrics', 'no-json')).toBeNull()
    expect(parseEvent('metrics', null)).toBeNull()
    expect(parseEvent('otro', { server_id: 1 })).toBeNull()
    expect(parseEvent('metrics', { foo: 1 })).toBeNull()
  })

  it('dispatch escribe en el cache e invalida en snapshot', () => {
    const client = new QueryClient()
    client.setQueryData(keys.servers, [base])
    client.setQueryData(keys.server(1), { server: base, sections: {} })
    dispatchEvent(client, { type: 'server.status', payload: { server_id: 1, status: 'agent_down', last_seen_at: null } })
    expect(client.getQueryData<ServerSummary[]>(keys.servers)?.[0]?.status).toBe('agent_down')
    expect(client.getQueryData<ServerDetail>(keys.server(1))?.server.status).toBe('agent_down')

    dispatchEvent(client, { type: 'snapshot', payload: { server_id: 1, section: 'pm2', collected_at: summary.collected_at } })
    expect(client.getQueryState(keys.server(1))?.isInvalidated).toBe(true)
  })

  it('una alerta invalida las alertas y la lista de servers', () => {
    const client = new QueryClient()
    client.setQueryData(keys.alerts('firing', 1), { data: [] })
    client.setQueryData(keys.servers, [base])
    client.setQueryData(keys.server(1), { server: base, sections: {} })
    const event = parseEvent('alert', { server_id: 1 })
    if (event) dispatchEvent(client, event)
    expect(client.getQueryState(keys.alerts('firing', 1))?.isInvalidated).toBe(true)
    expect(client.getQueryState(keys.servers)?.isInvalidated).toBe(true)
    expect(client.getQueryState(keys.server(1))?.isInvalidated).toBe(false)
  })

  it('un mensaje de un reporte invalida las listas, ese detalle, el roadmap y el número del menú', () => {
    const event = parseEvent('report.message', { report_id: 3, project_slug: 'playinn' })
    expect(event?.type).toBe('report.message')
    expect(parseEvent('report', { foo: 1 })).toBeNull()

    const client = new QueryClient()
    client.setQueryData(keys.reports('received', 1), { data: [] })
    client.setQueryData(keys.report(3), {})
    client.setQueryData(keys.reportsAttention, { count: 1 })
    client.setQueryData(keys.roadmap, [])
    client.setQueryData(keys.servers, [base])
    if (event) dispatchEvent(client, event)
    for (const key of [keys.reports('received', 1), keys.report(3), keys.reportsAttention, keys.roadmap]) {
      expect(client.getQueryState(key)?.isInvalidated).toBe(true)
    }
    expect(client.getQueryState(keys.servers)?.isInvalidated).toBe(false)
  })
})
