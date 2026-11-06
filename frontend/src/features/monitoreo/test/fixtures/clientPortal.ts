// El portal visto por el cliente: el subconjunto de monitoreo/web/src/mocks/portal.ts (Playinn y Club Olimpo) que usan
// los tests, ya como lo responde el back (partes Web/App/CMS, notas sin autor, el equipo como "Equipo nexosmart").
import type { CurrentUser } from '@/features/auth/types'
import type {
  ClientReport, Incident, LivePart, PageSpeedSummary, PortalComponent, PortalPart, PortalStatus, ReportMessage, ResponseRange, ResponseTimes, UptimeDay,
} from '../../api/types'
import { partsText } from '../../lib/parts'
import { ago } from './portal'

const inDays = (days: number) => new Date(Date.now() + days * 86_400_000).toISOString()

const clientUser = (id: number, email: string, client: { id: number; name: string }): CurrentUser => ({
  id, first_name: '', last_name: '', full_name: email, email, locale: 'es', timezone: 'America/Argentina/Buenos_Aires', role: { id: 9, name: 'Cliente' },
  permissions: [], company: { id: 1, name: 'nexosmart', currency: 'USD', timezone: 'UTC' }, client,
})
export const playinnUser = clientUser(101, 'cliente@playinn.com', { id: 1, name: 'Playinn' }) // un solo proyecto
export const olimpoUser = clientUser(103, 'cliente@olimpo.com.ar', { id: 2, name: 'Club Olimpo' }) // dos proyectos

// ---------- página de estado ----------

const localDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

// 90 días; los "días atrás" de `incidents` con sus minutos caído / degradado.
function uptimeDays(createdDaysAgo: number, incidents: Record<number, { down?: number; degraded?: number }>): UptimeDay[] {
  return Array.from({ length: 90 }, (_, index) => {
    const daysAgo = 89 - index
    const date = localDate(new Date(Date.now() - daysAgo * 86_400_000))
    if (daysAgo > createdDaysAgo) return { date, status: 'nodata', down_minutes: 0, degraded_minutes: 0 }
    const { down = 0, degraded = 0 } = incidents[daysAgo] ?? {}
    return { date, status: down > 0 ? 'down' : degraded > 0 ? 'degraded' : 'operational', down_minutes: down, degraded_minutes: degraded }
  })
}

// Solo "Caído" baja el porcentaje (D-039).
function uptimePercent(days: readonly UptimeDay[]): number | null {
  const counted = days.filter((day) => day.status !== 'nodata')
  if (counted.length === 0) return null
  const down = counted.reduce((sum, day) => sum + day.down_minutes, 0)
  return Math.round((100 - (down / (counted.length * 1440)) * 100) * 100) / 100
}

function uptime(days: UptimeDay[]): PortalStatus['uptime'] {
  const month = localDate(new Date()).slice(0, 7)
  return { days, month_pct: uptimePercent(days.filter((day) => day.date.startsWith(month))), range_pct: uptimePercent(days) }
}

const part = (id: number, kind: PortalPart, status: PortalComponent['status'], phrase: string, since: string): PortalComponent => ({
  id, label: partsText([kind]), kind, status, phrase, since, uptime_month_pct: 99.9,
})

const clock = (date: Date) => date.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hour12: false })
const dayMonth = (date: Date) => `${date.getDate()}/${date.getMonth() + 1}`

// Un tramo "Caído", con el título que arma el back por las partes afectadas. minutes null = sigue abierto.
function incident(id: number, parts: PortalPart[], startedAgo: number, minutes: number | null, notes: string[]): Incident {
  const started = new Date(Date.now() - startedAgo * 1000)
  const ended = minutes === null ? null : new Date(started.getTime() + minutes * 60_000)
  const subject = partsText(parts)
  const plural = parts.length > 1
  const title = ended
    ? `${subject} ${plural ? 'no respondieron' : 'no respondió'} el ${dayMonth(started)} entre las ${clock(started)} y las ${clock(ended)}.`
    : `${subject} ${plural ? 'no responden' : 'no responde'} desde el ${dayMonth(started)} a las ${clock(started)}.`
  return {
    id, component: subject, status: ended ? 'resolved' : 'ongoing', started_at: started.toISOString(), ended_at: ended?.toISOString() ?? null, title,
    notes: notes.map((body, index) => ({ id: id * 10 + index, body, created_at: ago(startedAgo - 120 * (index + 1)) })),
  }
}

// Celular con datos reales (CrUX) y computadora de laboratorio, 12 semanas de historia.
function playinnPageSpeed(): PageSpeedSummary {
  const history = Array.from({ length: 12 }, (_, index) => 11 - index).flatMap((weeksAgo, index) => [
    { measured_at: ago(weeksAgo * 7 * 86_400 + 2 * 86_400), strategy: 'mobile' as const, score: 60 + index },
    { measured_at: ago(weeksAgo * 7 * 86_400 + 2 * 86_400 - 600), strategy: 'desktop' as const, score: 82 + index },
  ])
  return {
    component: 'Web',
    mobile: { measured_at: ago(2 * 86_400), score: 71, lcp_ms: 2300, cls: 0.07, inp_ms: 180, fcp_ms: 1400, tbt_ms: 420, field: true },
    desktop: { measured_at: ago(2 * 86_400 - 600), score: 93, lcp_ms: 1211, cls: 0.01, inp_ms: null, fcp_ms: 800, tbt_ms: 30, field: false },
    history,
  }
}

const none = { checked_at: null, ssl: null, domain: null, backup: null, last_update: null, incidents: [], pagespeed: [] }

export function portalProjects(): (PortalStatus & { client_id: number; latency: Partial<Record<PortalPart, number>> })[] {
  return [
    {
      ...none, client_id: 1, latency: { web: 212, app: 260, cms: 180 },
      slug: 'playinn', name: 'Playinn', client_name: 'Playinn', primary_color: '#e11d48', logo_url: 'api/portal/projects/playinn/logo?v=1759320000',
      status: 'degraded', phrase: 'Degradado: problemas en App y CMS', incident: false, checked_at: ago(20),
      // La API afecta a la App y al CMS; ni ella, ni los workers, ni los backups le llegan al cliente como tales (D-045).
      components: [
        part(1, 'web', 'operational', 'Funciona normalmente', ago(14 * 86_400)),
        part(2, 'app', 'degraded', 'Responde lento', ago(25 * 60)),
        part(3, 'cms', 'degraded', 'Responde lento', ago(25 * 60)),
      ],
      uptime: uptime(uptimeDays(80, { 61: { down: 14 }, 23: { degraded: 45 }, 2: { down: 7, degraded: 20 }, 0: { degraded: 25 } })),
      ssl: { expires_at: inDays(61), days_left: 60 },
      domain: { name: 'playinn.com', expires_at: inDays(200), days_left: 199 },
      backup: { last_ok_at: ago(5 * 3600), status: 'operational' },
      last_update: { at: ago(3 * 86_400 + 4 * 3600) },
      // El de hace 61 días ya no le llega: el cliente ve 30 días.
      incidents: [incident(11, ['app', 'cms'], 2 * 86_400 + 3 * 3600, 7, ['Investigando: el proveedor de pagos no responde.', 'Resuelto: se reinició el servicio de colas y los pagos volvieron.'])],
      pagespeed: [playinnPageSpeed()],
    },
    {
      ...none, client_id: 2, latency: { web: 340 },
      slug: 'olimpo-web', name: 'Sitio del club', client_name: 'Club Olimpo', primary_color: '#0f766e', logo_url: null,
      status: 'operational', phrase: 'Todo funciona normalmente', incident: false, checked_at: ago(30),
      components: [part(1, 'web', 'operational', 'Funciona normalmente', ago(40 * 86_400))],
      uptime: uptime(uptimeDays(200, { 40: { down: 32 }, 11: { degraded: 18 } })),
      ssl: { expires_at: inDays(6), days_left: 5 },
      domain: { name: 'clubolimpo.com.ar', expires_at: inDays(25), days_left: 24 },
      backup: { last_ok_at: ago(31 * 3600), status: 'degraded' },
    },
    {
      ...none, client_id: 2, latency: { app: 95 },
      slug: 'olimpo-app', name: 'App de socios', client_name: 'Club Olimpo', primary_color: null, logo_url: null,
      status: 'down', phrase: 'Caído: App — no responde', incident: true, checked_at: ago(25),
      components: [part(1, 'app', 'down', 'No responde', ago(9 * 60))],
      uptime: uptime(uptimeDays(5, { 0: { down: 9 } })),
      // Primero el resuelto: el front pone los abiertos arriba.
      incidents: [incident(13, ['app'], 3 * 86_400, 12, []), incident(14, ['app'], 9 * 60, null, ['Investigando: la base de datos no acepta conexiones.'])],
    },
  ]
}

// Determinístico: el mismo rango da siempre la misma curva. Una serie por parte con sitio web.
export function responseTimes(latency: Partial<Record<PortalPart, number>>, range: ResponseRange): ResponseTimes {
  const step = range === '24h' ? 300 : 3600
  const count = range === '24h' ? 288 : range === '7d' ? 168 : 720
  const end = Math.floor(Date.now() / 1000 / step) * step
  const series = (Object.entries(latency) as [PortalPart, number][]).map(([kind, base]) => ({
    label: partsText([kind]),
    kind,
    points: Array.from({ length: count }, (_, index) => {
      const avg = Math.round(base * (1 + 0.25 * Math.sin(index / 9)) + (index % 7) * 3)
      return { ts: new Date((end - (count - 1 - index) * step) * 1000).toISOString(), avg_ms: avg, max_ms: Math.round(avg * 1.6) }
    }),
  }))
  return { range, resolution: range === '24h' ? '5m' : '1h', series }
}

// Lo que vio el chequeo en vivo de Playinn, en sus partes.
export const playinnLive: { status: PortalStatus['status']; message: string; components: LivePart[] } = {
  status: 'degraded',
  message: 'Detectamos problemas en la App y el CMS. El equipo ya está avisado.',
  components: [
    { label: 'Web', status: 'operational', phrase: 'Funciona normalmente' },
    { label: 'App', status: 'degraded', phrase: 'Responde lento' },
    { label: 'CMS', status: 'degraded', phrase: 'Responde lento' },
  ],
}

// ---------- reportes de Playinn ----------

export type ClientReportFixture = Omit<ClientReport, 'unread'> & { slug: string }

// El más nuevo primero, como el back. El 2 (resuelto, con captura) tiene una respuesta que el cliente ya leyó; el 3, una que no.
export function playinnReports(): ClientReportFixture[] {
  return [
    {
      id: 3, slug: 'playinn', created_at: ago(26 * 3600), updated_at: ago(20 * 3600), resolved_at: null, urgency: 'question', status: 'in_review',
      description: '¿Se puede agregar un campo de DNI en el formulario de registro de jugadores?', component_label: null, screenshot_url: null,
    },
    {
      id: 2, slug: 'playinn', created_at: ago(3 * 86_400), updated_at: ago(3 * 86_400 - 5400), resolved_at: ago(3 * 86_400 - 5400), urgency: 'blocking',
      status: 'resolved', description: 'No podemos cobrar: al confirmar una reserva el botón de pagar se queda cargando y no pasa nada.',
      component_label: 'App', screenshot_url: 'api/portal/projects/playinn/reports/2/screenshot',
    },
  ]
}

// Con `internal` y el autor real, como los guarda el back; al cliente le llegan filtrados (clientMessages).
export const playinnMessages = (): Record<number, ReportMessage[]> => ({
  2: [{ id: 31, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: 'Ya está: el proveedor de pagos volvió y se cobraron las reservas pendientes.', created_at: ago(3 * 86_400 - 5400), images: [], internal: false }],
  3: [{ id: 32, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: 'Sí, se puede. ¿Lo querés obligatorio u opcional?', created_at: ago(20 * 3600), images: [], internal: false }],
})
