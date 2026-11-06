// Reportes de ejemplo de monitoreo/web/src/mocks/portal.ts: uno nuevo de Club Olimpo y, en Playinn, uno resuelto
// (urgente, con captura) y otro en revisión.
import type { PortalState, ReportDetail, ReportMessage, ServerSnapshot } from '../../api/types'
import { ago } from './portal'

export type ReportFixture = Omit<ReportDetail, 'messages' | 'unread'>

const op = { status: 'operational' as const, phrase: 'Funciona normalmente' }
const slow = { status: 'degraded' as const, phrase: 'Responde lento' }
const live = (parts: ReportDetail['live_check']['parts'], message: string, components: ReportDetail['live_check']['components'], at: string) => ({
  status: parts.some((part) => part.status === 'degraded') ? ('degraded' as const) : ('operational' as const), message, checked_at: at, parts, components,
})
const tech = (label: string, kind: string, parts: ReportDetail['live_check']['components'][number]['parts'], state: { status: PortalState; phrase: string }, latency: number | null) => ({
  label, kind, parts, ...state, detail: state === slow ? '1.840 ms (umbral 1.500 ms)' : 'HTTP 200', latency_ms: latency, status_code: latency === null ? null : 200,
})

const playinnLive = (at: string) =>
  live(
    [{ label: 'Web', ...op }, { label: 'App', ...slow }, { label: 'CMS', ...slow }],
    'Detectamos problemas en la App y el CMS. El equipo ya está avisado.',
    [
      tech('Web', 'http', ['web'], op, 212), tech('App', 'http', ['app'], op, 260), tech('CMS', 'http', ['cms'], op, 180),
      tech('API', 'health', ['app', 'cms'], slow, 1840), tech('Workers', 'supervisor', ['app'], op, null), tech('Backups', 'timer', [], op, null),
    ],
    at,
  )

// El server "clientes" (id 5) como lo vio el back al reportar (D-050): solo los procesos del proyecto.
export function serverSnapshot(takenAt: string): ServerSnapshot {
  return {
    taken_at: takenAt,
    servers: [{
      id: 5, name: 'clientes', status: 'online', last_seen_at: takenAt,
      metrics: { cpu_percent: 38.6, memory_percent: 81.2, swap_percent: 12.4, disk_percent: 70, load1: 1.42, uptime_seconds: 41 * 86_400, collected_at: takenAt },
      processes: [
        { kind: 'pm2', name: 'playinn-api', state: 'online', healthy: true, restarts: 2, cpu_percent: 21.3, memory_bytes: 312 * 1024 * 1024 },
        { kind: 'pm2', name: 'playinn-cms', state: 'errored', healthy: false, restarts: 15, cpu_percent: 0, memory_bytes: 0 },
        { kind: 'supervisor', name: 'playinn-queue:playinn-queue_00', state: 'RUNNING', healthy: true },
      ],
      services: [{ name: 'nginx', active: 'active', healthy: true }, { name: 'mysql', active: 'active', healthy: true }, { name: 'redis-server', active: 'failed', healthy: false }],
      alerts: [{
        id: 31, server_id: 5, server_name: 'clientes', rule: 'pm2_state', target: 'playinn-cms', severity: 'critical', state: 'firing',
        message: 'PM2: playinn-cms en errored', value: 'errored', first_seen_at: takenAt, last_seen_at: takenAt, resolved_at: null, last_notified_at: takenAt,
      }],
    }],
  }
}

const browser = { user_agent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1', page_url: '', screen: '390x844', timezone: 'America/Argentina/Buenos_Aires', language: 'es-AR' }

function base(id: number, project: [number, number, string, string], email: string, seconds: number, extra: Partial<ReportFixture>): ReportFixture {
  const [projectId, clientId, clientName, projectName] = project
  const at = ago(seconds)
  return {
    id, created_at: at, updated_at: at, resolved_at: null, urgency: 'annoying', status: 'received', description: '', client_id: clientId, client_name: clientName,
    project_id: projectId, project_name: projectName, user_email: email, component_label: null, has_screenshot: false, ip: '190.2.33.14',
    browser: { ...browser, page_url: `https://clientes.nexosmart.com.ar/portal/${projectName.toLowerCase()}` },
    live_check: playinnLive(at), open_issues: [{ label: 'API', status: 'degraded', since: ago(25 * 60), detail: '1.840 ms (umbral 1.500 ms)' }],
    recent_errors: null, screenshot_url: null, server_snapshot: serverSnapshot(at), ...extra,
  }
}

const curl = (timestamp: string, ms: number) => ({
  timestamp, level: 'ERROR', message: `cURL error 28: Operation timed out after ${ms} milliseconds (Mercado Pago)`, method: 'POST',
  url: 'https://api.playinn.com/api/payments', exception_class: 'GuzzleHttp\\Exception\\ConnectException',
})

// El más nuevo primero, como el back.
export function initialReports(): ReportFixture[] {
  const playinn: [number, number, string, string] = [1, 1, 'Playinn', 'Playinn']
  return [
    base(1, [2, 2, 'Club Olimpo', 'Sitio del club'], 'cliente@olimpo.com.ar', 2 * 3600, {
      description: 'La página de socios tarda mucho en cargar desde el celular, sobre todo a la noche.', component_label: 'Web', open_issues: [],
      live_check: live([{ label: 'Web', ...op }], 'Desde nuestro lado todo responde bien. Ya recibimos tu reporte y lo revisamos.', [tech('Web', 'http', ['web'], op, 340)], ago(2 * 3600)),
    }),
    base(3, playinn, 'cliente@playinn.com', 26 * 3600, {
      urgency: 'question', status: 'in_review', updated_at: ago(20 * 3600), description: '¿Se puede agregar un campo de DNI en el formulario de registro de jugadores?',
      recent_errors: { error: 'El /api/errors del proyecto no respondió (timeout).', items: [] }, server_snapshot: null,
    }),
    base(2, playinn, 'cliente@playinn.com', 3 * 86_400, {
      urgency: 'blocking', status: 'resolved', resolved_at: ago(3 * 86_400 - 5400), updated_at: ago(3 * 86_400 - 5400),
      description: 'No podemos cobrar: al confirmar una reserva el botón de pagar se queda cargando y no pasa nada.', component_label: 'App',
      has_screenshot: true, screenshot_url: 'api/portal-admin/reports/2/screenshot',
      recent_errors: { error: null, items: [curl('2026-10-04 21:14:03', 30001), curl('2026-10-04 21:12:41', 30000)] },
    }),
  ]
}

// El 2 (resuelto) tiene una respuesta que el cliente ya leyó; el 3, una que todavía no.
export const initialMessages = (): Record<number, ReportMessage[]> => ({
  2: [{ id: 31, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: 'Ya está: el proveedor de pagos volvió y se cobraron las reservas pendientes.', created_at: ago(3 * 86_400 - 5400), images: [], internal: false }],
  3: [{ id: 32, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: 'Sí, se puede. ¿Lo querés obligatorio u opcional?', created_at: ago(20 * 3600), images: [], internal: false }],
})
