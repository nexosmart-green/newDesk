// Tipos de la API del panel (docs/panel-api.md).
import type { Metrics, Section, SectionData, VulnCounts, VulnDir } from './contract'

export interface Envelope<T> {
  data: T
  message: string
  errors: Record<string, string[]> | null
  meta?: PageMeta
}
export interface PageMeta { page: number; per_page: number; total: number }

// client: null = equipo; con datos = usuario de un cliente (solo ve /portal).
// discord_user_id: la cuenta de Discord con la que el bot lo etiqueta en los avisos de sus proyectos.
export interface User {
  id: number; email: string; is_admin: boolean; active: boolean; totp_enabled: boolean
  created_at: string; last_login_at: string | null; client: { id: number; name: string } | null
  discord_user_id: string | null
}

export type ServerStatus = 'online' | 'agent_down' | 'down' | 'unknown' | 'disabled'

export interface MetricsSummary {
  cpu_percent: number; memory_percent: number; swap_percent: number; disk_percent: number
  load1: number; uptime_seconds: number; collected_at: string
}

export interface ServerSummary {
  id: number; name: string; ip: string; ssh_port: number | null; agent_port: number; enabled: boolean
  status: ServerStatus; last_seen_at: string | null; hostname: string | null; os: string | null
  metrics: MetricsSummary | null; active_alerts: number; agent_version: string | null
}

// De qué proyecto de cliente es una carpeta del inventario (D-044): una carpeta, un solo proyecto.
export interface FolderLink { project_id: number; project_name: string; client_id: number; client_name: string }

// GET /api/inventory/projects: carpetas de proyecto de todos los servers, para el buscador.
// GET /api/portal-admin/folders devuelve lo mismo (FolderOption) para vincularlas a un proyecto de cliente.
export interface IndexedProject {
  server_id: number; server_name: string; server_status: ServerStatus; path: string; name: string; type: string
  framework_version?: string; branch?: string; domains: string[]; subapps: string[]
  linked: FolderLink | null
}
export type FolderOption = IndexedProject

// data, collected_at y agent_version llegan en null si el primer intento falló (solo hay error).
export interface SectionSnapshot<T> {
  collected_at: string | null; fetched_at: string; agent_version: string | null; data: T | null
  warnings: string[] | null; error: string | null
}

export type Sections = { [K in Section]?: SectionSnapshot<SectionData[K]> | null }

export interface ServerDetail { server: ServerSummary; sections: Sections }

export interface ServerInput {
  name: string; ip: string; ssh_port?: number | null; agent_port: number
  token?: string; tls_fingerprint?: string; enabled?: boolean
}

export interface TestResult { ok: boolean; latency_ms?: number; agent_version?: string; hostname?: string; error?: string }

export type MetricsRange = '1h' | '24h' | '7d' | '30d' | '1y'
export interface MetricPoint {
  ts: string; cpu: number; cpu_max?: number; memory: number; swap: number
  disk: number; load1: number; rx_bps: number; tx_bps: number
}
export interface MetricsSeries { range: MetricsRange; resolution: 'raw' | 'hourly'; points: MetricPoint[] }

export interface SiteCheck {
  url: string; server_name: string; status_code: number | null; latency_ms: number | null; ok: boolean
  error: string | null; ssl_expires_at: string | null; ssl_days_left: number | null; checked_at: string
}

export type AlertState = 'firing' | 'resolved'
export interface Alert {
  id: number; server_id: number; server_name: string; rule: string; target: string
  severity: 'critical' | 'warning'; state: AlertState; message: string; value: string
  first_seen_at: string; last_seen_at: string; resolved_at: string | null; last_notified_at: string | null
}

export interface Session { id: string; ip: string; user_agent: string; created_at: string; expires_at: string; current: boolean }

// Token personal para conectar Claude por MCP (D-050). `token` (texto plano) llega solo al crearlo.
export interface APIToken { id: string; name: string; created_at: string; last_used_at: string | null }
export interface APITokenCreated extends APIToken { token: string }

// done: usuario de cliente, entra sin autenticador (ya tiene la sesión).
export type NextStep = 'totp' | 'enroll' | 'done'
export interface Enrollment { secret: string; otpauth_url: string; qr_png_base64: string }

// Eventos SSE
export interface StatusEvent { server_id: number; status: ServerStatus; last_seen_at: string | null }
export interface MetricsEvent { server_id: number; metrics: MetricsSummary; full?: Metrics }
export interface SnapshotEvent { server_id: number; section: Section; collected_at: string }

// ---------- Portal de clientes (docs/panel-api.md → Portal de clientes) ----------

export type PortalState = 'operational' | 'degraded' | 'down' | 'unknown'
// Las partes del proyecto como las nombra el cliente (D-045): solo las que tiene, en este orden.
export type PortalPart = 'web' | 'app' | 'cms'
export type ComponentKind = 'http' | 'health' | 'pm2' | 'supervisor' | 'timer'

export interface PortalCard {
  slug: string; name: string; primary_color: string | null; logo_url: string | null; status: PortalState; phrase: string
  // El equipo ya está resolviendo una caída: el cliente ve el aviso.
  incident: boolean
}
// Lo que ve el cliente son las partes (Web, App, CMS), no los componentes técnicos.
export interface PortalComponent {
  id: number; label: string; kind: PortalPart; status: PortalState; phrase: string; since: string | null; uptime_month_pct: number | null
}
export interface UptimeDay { date: string; status: 'operational' | 'degraded' | 'down' | 'nodata'; down_minutes: number; degraded_minutes: number }
export interface PortalStatus extends PortalCard {
  client_name: string; checked_at: string | null; components: PortalComponent[]
  uptime: { days: UptimeDay[]; month_pct: number | null; range_pct: number | null }
  ssl: { expires_at: string; days_left: number } | null
  domain: { name: string; expires_at: string | null; days_left: number | null } | null
  backup: { last_ok_at: string | null; status: PortalState } | null
  // Etapas 3 y 4 (D-042, D-043).
  // Solo la fecha: el cliente no ve nombres de carpetas (D-044).
  last_update: { at: string } | null
  incidents: Incident[]
  pagespeed: PageSpeedSummary[]
}

// Un incidente es un tramo "Caído" de un componente; el título viene armado del back en hora de Buenos Aires.
// `author` solo viene en las vistas del equipo.
export interface IncidentNote { id: number; body: string; created_at: string; author?: string }
export interface Incident {
  id: number; component: string; status: 'ongoing' | 'resolved'; started_at: string; ended_at: string | null; title: string
  notes: IncidentNote[]
}

export type PageSpeedStrategy = 'mobile' | 'desktop'
// field = datos reales de usuarios (CrUX); false = laboratorio (sin INP).
export interface PageSpeedRun {
  measured_at: string; score: number; lcp_ms: number | null; cls: number | null; inp_ms: number | null
  fcp_ms: number | null; tbt_ms: number | null; field: boolean
}
export interface PageSpeedSummary {
  component: string; mobile: PageSpeedRun | null; desktop: PageSpeedRun | null
  history: { measured_at: string; strategy: PageSpeedStrategy; score: number }[]
}

export type ResponseRange = '24h' | '7d' | '30d'
export interface ResponsePoint { ts: string; avg_ms: number; max_ms: number }
export interface ResponseTimes {
  range: ResponseRange; resolution: '5m' | '1h'
  series: { label: string; kind: PortalPart; points: ResponsePoint[] }[]
}

// Carpeta del inventario vinculada a un proyecto de cliente; da la "última actualización".
// types: el de la carpeta y los de sus subapps (laravel, nextjs…).
export interface ProjectFolder { server_id: number; server_name: string; path: string; name: string; types: string[]; last_update_at: string | null }
export interface FolderRef { server_id: number; path: string }

// Lo que el back propone a partir de las carpetas vinculadas. exists = ya hay un componente igual.
export interface Suggestion {
  kind: ComponentKind; label: string; parts: PortalPart[]; url?: string | null; server_id?: number | null; server_name?: string | null
  target?: string | null; target_owner?: string | null; reason: string; exists: boolean
}

export interface ClientRow { id: number; name: string; active: boolean; created_at: string; users: number; projects: number }
export interface ProjectRow {
  id: number; client_id: number; client_name: string; slug: string; name: string; active: boolean; status: PortalState; components: number
  logo_url: string | null // api/portal-admin/projects/<id>/logo?v=… (D-048)
  developers: Developer[]
  vulns: VulnCounts // del último security-scan.sh de sus carpetas (D-053)
}
// Desarrolladores del proyecto (D-053): el MCP trae por defecto los proyectos de quien lo usa.
export interface Developer { user_id: number; email: string; role: 'lead' | 'secondary' }
export interface DevelopersInput { lead_user_id: number | null; secondary_user_ids: number[] }

export interface VulnDirView extends VulnDir { server_id: number; server_name: string; scanned_at: string | null }
export interface ProjectVulns {
  counts: VulnCounts
  // Los servers de las carpetas vinculadas; has_scan false = ese server no manda resultados.
  servers: { server_id: number; server_name: string; has_scan: boolean; scanned_at: string | null }[]
  dirs: VulnDirView[]
}
export interface ClientDetail { client: ClientRow; users: User[]; projects: ProjectRow[] }

// parts: a qué partes del cliente afecta (un timer, ninguna). Sin parts, el back las deduce (D-045).
// target_owner: en pm2, el usuario del daemon. Sin él, el monitor lo fija si un solo usuario corre esa app.
export interface ComponentInput {
  kind: ComponentKind; label: string; parts?: PortalPart[]; position?: number; url?: string; server_id?: number; target?: string
  target_owner?: string | null; max_age_hours?: number; slow_ms?: number
}
// Los campos de ComponentInput que no aplican al tipo pueden venir en null.
export interface ComponentAdmin {
  id: number; project_id: number; kind: ComponentKind; label: string; parts: PortalPart[]; position: number
  url?: string | null; server_id?: number | null; server_name: string | null; target?: string | null; target_owner?: string | null
  max_age_hours?: number | null; slow_ms?: number | null
  status: PortalState; status_since: string | null; detail: string | null; last_checked_at: string | null
  last_ok_at: string | null; latency_ms: number | null; status_code: number | null; ssl_expires_at: string | null
}
export interface ProjectInput {
  name: string; slug: string; primary_color: string | null; domain: string | null; domain_expires_manual: string | null; active: boolean
  // /api/errors del bug-report del proyecto: null o "" borra la URL y el token; el token vacío o ausente no cambia.
  errors_url?: string | null; errors_token?: string
  // Canal de Discord del proyecto, de la lista del bot. Ausente = no cambia, null o "" = sin canal.
  discord_channel_id?: string | null
  developers?: DevelopersInput // ausente = no se tocan
}
export interface ProjectAdmin extends Omit<ProjectInput, 'errors_url' | 'errors_token' | 'discord_channel_id' | 'developers'> {
  id: number; client_id: number; client_name: string; has_logo: boolean; logo_updated_at: string | null
  domain_expires_at: string | null; domain_checked_at: string | null; status: PortalState; phrase: string; components: ComponentAdmin[]
  errors_url: string | null; has_errors_token: boolean; discord_channel_id: string | null
  folders: ProjectFolder[]
  developers: Developer[]
}
// create_discord_channel: el bot crea el canal y lo estrena etiquetando al dev principal.
// Si no puede, el proyecto se crea igual y el message del sobre lo avisa.
export interface ProjectCreateInput { name: string; slug?: string; lead_user_id?: number; discord_channel_id?: string; create_discord_channel?: boolean }

// Lo que ve el bot de Discord (GET /api/discord/channels y /members). Sin bot configurado, 503.
// can_send false = el bot ve el canal pero no puede escribir ahí.
export interface DiscordChannel { id: string; name: string; kind: 'channel' | 'thread'; category: string | null; can_send: boolean }
export interface DiscordMember { id: string; username: string; display_name: string }
// Una app de PM2 y el usuario dueño de su daemon: dos usuarios pueden tener apps con el mismo nombre.
export interface PM2Target { name: string; owner: string }
export interface ServerTargets { server_id: number; server_name: string; pm2: PM2Target[]; supervisor: string[]; timers: string[] }

// ---------- Reportar un problema (docs/panel-api.md → Reportar un problema) ----------

export type ReportUrgency = 'blocking' | 'annoying' | 'question'
export type ReportStatus = 'received' | 'in_review' | 'resolved'

export interface ClientReport {
  id: number; created_at: string; updated_at: string; resolved_at: string | null; urgency: ReportUrgency; description: string
  component_label: string | null; status: ReportStatus
  // unread: el equipo escribió después de la última vez que el cliente lo abrió.
  unread: boolean; screenshot_url: string | null
}
// images: rutas de la API (se cargan con apiUrl). Al cliente, el autor del equipo le llega como "Equipo nexosmart".
export interface ReportMessage { id: number; author_kind: 'client' | 'team'; author: string; body: string; created_at: string; images: string[]; internal?: boolean }
export interface ClientReportDetail { report: ClientReport; messages: ReportMessage[] }
export interface LivePart { label: string; status: PortalState; phrase: string }
// components: las partes que vio el cliente (Web, App, CMS).
export interface LiveCheck { status: PortalState; message: string; components: LivePart[] }
export interface ReportCreated { report: ClientReport; live: LiveCheck }

export interface ReportRow {
  id: number; created_at: string; urgency: ReportUrgency; status: ReportStatus; description: string
  client_id: number; client_name: string; project_id: number; project_name: string
  user_email: string | null; component_label: string | null; has_screenshot: boolean
  // unread: el cliente escribió después de la última vez que el equipo lo abrió.
  unread: boolean
}
export interface ReportDetail extends ReportRow {
  updated_at: string; resolved_at: string | null; ip: string
  browser: { user_agent: string; page_url: string; screen: string; timezone: string; language: string }
  live_check: {
    status: PortalState; message: string; checked_at: string
    // Los componentes técnicos con las partes a las que afectan; `parts` es lo que vio el cliente.
    components: {
      label: string; kind: string; parts: PortalPart[]; status: PortalState; phrase: string; detail: string
      latency_ms: number | null; status_code: number | null
    }[]
    parts: LivePart[]
  }
  open_issues: { label: string; status: PortalState; since: string | null; detail: string }[]
  recent_errors: null | {
    error: string | null
    items: { timestamp: string; level: string; message: string; method: string; url: string; exception_class: string | null }[]
  }
  screenshot_url: string | null
  messages: ReportMessage[]
  // Los servidores del proyecto en el momento del reporte (D-050); null si no tenía servidor.
  server_snapshot: ServerSnapshot | null
}

export interface ServerSnapshot { taken_at: string; servers: ServerAtReport[] }
export interface ServerAtReport {
  id: number; name: string; status: ServerStatus; last_seen_at: string | null; metrics: MetricsSummary | null
  processes: { kind: 'pm2' | 'supervisor'; name: string; state: string; healthy: boolean; restarts?: number; cpu_percent?: number; memory_bytes?: number }[]
  services: { name: string; active: string; healthy: boolean }[]
  alerts: Alert[]
}

// "Generar reporte" (D-067, D-068, D-074). reason: por qué el botón está deshabilitado (el Claude de root no está conectado al panel).
export interface DailyReportStatus { available: boolean; reason: string }
// markdown: los títulos que armó Claude. empty: hoy no hubo commits tuyos (no se llama a Claude).
export interface DailyReport { markdown: string; projects: { name: string; commits: number }[]; empty: boolean }
