// Clientes, proyectos y equipo de ejemplo: el subconjunto de monitoreo/web/src/mocks/{portal,data}.ts que usan los tests.
import type { CurrentUser } from '@/features/auth/types'
import type {
  ClientRow, ComponentAdmin, DiscordChannel, FolderOption, Incident, ProjectAdmin, ProjectRow, ProjectVulns, Suggestion, User,
} from '../../api/types'

export const ago = (seconds: number) => new Date(Date.now() - seconds * 1000).toISOString()
const W = '/var/www/clientes.nexosmart.com.ar'

// Un admin del módulo (monitoring.manage), como el admin de monitoreo.
export const teamAdmin: CurrentUser = {
  id: 1, first_name: 'Team', last_name: 'Violet', full_name: 'Team Violet', email: 'teamviolet@nexosmart.com.ar', locale: 'es', timezone: 'UTC',
  role: { id: 1, name: 'Admin' }, permissions: ['monitoring.view', 'monitoring.manage'], company: { id: 1, name: 'nexosmart', currency: 'USD', timezone: 'UTC' },
  two_factor: { enabled: false, required: false }, client: null,
}

const teamUser = (id: number, email: string, discord: string | null): User => ({
  id, email, is_admin: id === 1, active: true, totp_enabled: false, created_at: '2026-09-30T12:00:00Z', last_login_at: null, client: null, discord_user_id: discord,
})
export const team: User[] = [teamUser(1, 'teamviolet@nexosmart.com.ar', '1403351571303104612'), teamUser(2, 'dev@nexosmart.com.ar', null)]

export const PLAYINN_CHANNEL_ID = '1427806155307745290'
export const OLIMPO_CHANNEL_ID = '1427806268331659334'
export const discordChannels = (): DiscordChannel[] => [
  { id: PLAYINN_CHANNEL_ID, name: 'playinn', kind: 'channel', category: 'Equipo BBG1', can_send: true },
  { id: '1427806212559994931', name: 'reaquila', kind: 'channel', category: 'Equipo BBG1', can_send: false },
  { id: OLIMPO_CHANNEL_ID, name: 'club-olimpo', kind: 'channel', category: 'Equipo BBG1', can_send: true },
  { id: '1403352040079560744', name: 'general', kind: 'channel', category: 'General', can_send: true },
  { id: '1419713302480945222', name: 'Monitoring', kind: 'thread', category: 'General', can_send: true },
  { id: '1403352040079560745', name: 'bienvenida', kind: 'channel', category: null, can_send: true },
]

export const clients: ClientRow[] = [
  { id: 1, name: 'Playinn', active: true, created_at: '2026-07-01T12:00:00Z', users: 2, projects: 1 },
  { id: 2, name: 'Club Olimpo', active: true, created_at: '2026-07-01T12:00:00Z', users: 1, projects: 2 },
  { id: 3, name: 'Tooki', active: false, created_at: '2026-07-01T12:00:00Z', users: 1, projects: 0 },
]

export function component(projectId: number, id: number, extra: Partial<ComponentAdmin>): ComponentAdmin {
  return {
    id, project_id: projectId, kind: 'http', label: '', parts: [], position: 0, url: null, server_id: null, server_name: null, target: null,
    target_owner: null, max_age_hours: null, slow_ms: null, status: 'operational', status_since: ago(86_400), detail: null, last_checked_at: ago(20),
    last_ok_at: ago(20), latency_ms: null, status_code: null, ssl_expires_at: null, ...extra,
  }
}

const project = (id: number, clientId: number, clientName: string, slug: string, name: string, extra: Partial<ProjectAdmin>): ProjectAdmin => ({
  id, client_id: clientId, client_name: clientName, slug, code_prefix: slug.replace(/[^a-z0-9]/g, '').slice(0, 12), name, primary_color: null,
  has_logo: false, logo_updated_at: null, domain: null, domain_expires_at: null, domain_expires_manual: null, domain_checked_at: null, active: true,
  errors_url: null, has_errors_token: false, discord_channel_id: null, status: 'operational', phrase: 'Todo funciona normalmente', components: [],
  folders: [], developers: [], ...extra,
})

// Los proyectos tal como los devuelve GET portal-admin/projects/:id. Mutables: los handlers los cambian.
export function initialProjects(): ProjectAdmin[] {
  return [
    project(1, 1, 'Playinn', 'playinn', 'Playinn', {
      primary_color: '#e11d48', has_logo: true, logo_updated_at: '2026-10-01T12:00:00Z', domain: 'playinn.com', domain_expires_at: ago(-200 * 86_400),
      errors_url: 'https://api.playinn.com/api/errors', has_errors_token: true, discord_channel_id: PLAYINN_CHANNEL_ID, status: 'degraded',
      folders: [{ server_id: 5, server_name: 'clientes', path: `${W}/playinn`, name: 'playinn', types: ['monorepo', 'laravel', 'nextjs'], last_update_at: ago(3 * 86_400 + 4 * 3600) }],
      developers: [{ user_id: 1, email: 'teamviolet@nexosmart.com.ar', role: 'lead' }],
      components: [
        component(1, 1, { label: 'Web', parts: ['web'], url: 'https://playinn.com', latency_ms: 212, status_code: 200 }),
        component(1, 9, { label: 'App', parts: ['app'], position: 1, url: 'https://app.playinn.com', latency_ms: 260, status_code: 200 }),
        component(1, 8, { label: 'CMS', parts: ['cms'], position: 2, url: 'https://cms.playinn.com', latency_ms: 180, status_code: 200 }),
        component(1, 2, { kind: 'health', label: 'API', parts: ['app', 'cms'], position: 3, url: 'https://api.playinn.com/health', status: 'degraded', latency_ms: 1840 }),
        component(1, 3, { kind: 'supervisor', label: 'Workers', parts: ['app'], position: 4, server_id: 5, server_name: 'clientes', target: 'playinn-queue' }),
        component(1, 4, { kind: 'timer', label: 'Backups', position: 5, server_id: 5, server_name: 'clientes', target: 'backup-playinn.timer', max_age_hours: 26 }),
      ],
    }),
    project(2, 2, 'Club Olimpo', 'olimpo-web', 'Sitio del club', {
      primary_color: '#0f766e', domain: 'clubolimpo.com.ar',
      folders: [{ server_id: 1, server_name: 'reaquila', path: '/var/www/clubolimpo.com.ar', name: 'clubolimpo.com.ar', types: ['php'], last_update_at: null }],
      components: [component(2, 5, { label: 'Web', parts: ['web'], url: 'https://www.clubolimpo.com.ar' })],
    }),
    project(3, 2, 'Club Olimpo', 'olimpo-app', 'App de socios', {
      status: 'down', components: [component(3, 7, { label: 'App', parts: ['app'], url: 'https://app.clubolimpo.com.ar', status: 'down' })],
    }),
  ]
}

const scanned = ago(5 * 3600)
const zero = { critical: 0, high: 0, moderate: 0, low: 0 }
export const olimpoVulns: ProjectVulns = {
  counts: { critical: 0, high: 1, moderate: 0, low: 1 },
  servers: [{ server_id: 1, server_name: 'reaquila', has_scan: true, scanned_at: scanned }],
  dirs: [
    {
      server_id: 1, server_name: 'reaquila', scanned_at: scanned, path: '/var/www/clubolimpo.com.ar/api', manager: 'composer', counts: { critical: 0, high: 1, moderate: 0, low: 1 },
      findings: [
        { package: 'league/commonmark', severity: 'high', title: 'league/commonmark allows cross-site scripting via embed extension', cve: 'CVE-2025-46734', url: 'https://github.com/advisories/GHSA-3527-qv2q-pfvx' },
        { package: 'swiftmailer/swiftmailer', severity: 'low', title: 'Paquete abandonado, usar symfony/mailer' },
      ],
    },
    { server_id: 1, server_name: 'reaquila', scanned_at: scanned, path: '/var/www/clubolimpo.com.ar/landing-olimpo', manager: 'npm', counts: zero, findings: [] },
    {
      server_id: 1, server_name: 'reaquila', scanned_at: scanned, path: '/var/www/clubolimpo.com.ar/olimpo_app', manager: 'npm', counts: zero, findings: [],
      error: 'npm audit: request to https://registry.npmjs.org/-/npm/v1/security/advisories/bulk failed',
    },
  ],
}

// Las vulnerabilidades de cada proyecto, como las arma el back: solo reaquila manda resultados.
export const vulnsFor = (item: ProjectAdmin): ProjectVulns =>
  item.id === 2 ? olimpoVulns : { counts: zero, servers: item.folders.map((each) => ({ server_id: each.server_id, server_name: each.server_name, has_scan: false, scanned_at: null })), dirs: [] }

export function projectRow(item: ProjectAdmin): ProjectRow {
  return {
    id: item.id, client_id: item.client_id, client_name: item.client_name, slug: item.slug, name: item.name, active: item.active, status: item.status,
    components: item.components.length, developers: item.developers, vulns: vulnsFor(item).counts,
    logo_url: item.has_logo ? `api/portal-admin/projects/${item.id}/logo?v=1759320000` : null,
  }
}

const folderOption = (server: [number, string], path: string, type: string, domains: string[] = []): FolderOption => ({
  server_id: server[0], server_name: server[1], server_status: 'online', path, name: path.split('/').at(-1) ?? path, type, domains, subapps: [], linked: null,
})
const clientes: [number, string] = [5, 'clientes']
export const folderInventory: FolderOption[] = [
  folderOption(clientes, `${W}/playinn`, 'monorepo', ['playinn.com', 'app.playinn.com']),
  folderOption(clientes, `${W}/medplace-app`, 'nextjs'),
  folderOption(clientes, `${W}/medplace-cms`, 'laravel'),
  folderOption([1, 'reaquila'], '/var/www/clubolimpo.com.ar', 'php', ['clubolimpo.com.ar']),
  folderOption([2, 'cms-demo'], '/var/www/demo', 'laravel'),
]

// Lo que el back propone a partir de las carpetas de Playinn (sin `exists`: sale de los componentes).
export const playinnSuggestions: Omit<Suggestion, 'exists'>[] = [
  { kind: 'http', label: 'Web', parts: ['web'], url: 'https://playinn.com', reason: `playinn.com lo sirve clientes · ${W}/playinn` },
  { kind: 'supervisor', label: 'Workers', parts: ['web', 'app', 'cms'], server_id: 5, server_name: 'clientes', target: 'playinn-queue', reason: 'corre desde playinn en clientes' },
  { kind: 'supervisor', label: 'Workers', parts: ['web', 'app', 'cms'], server_id: 5, server_name: 'clientes', target: 'playinn-scheduler', reason: 'corre desde playinn en clientes' },
  { kind: 'pm2', label: 'App', parts: ['web', 'app', 'cms'], server_id: 5, server_name: 'clientes', target: 'playinn-web', target_owner: 'root', reason: 'corre desde playinn en clientes' },
]

const note = (id: number, body: string, secondsAgo: number) => ({ id, body, created_at: ago(secondsAgo), author: 'teamviolet@nexosmart.com.ar' })
export const playinnIncidents = (): Incident[] => [
  {
    id: 11, component: 'API', status: 'resolved', started_at: ago(2 * 86_400 + 3 * 3600), ended_at: ago(2 * 86_400 + 3 * 3600 - 420),
    title: 'App y CMS no respondieron el 4/11 entre las 10:00 y las 10:07.',
    notes: [note(21, 'Investigando: el proveedor de pagos no responde.', 2 * 86_400 + 3 * 3600 - 120), note(22, 'Resuelto: se reinició el servicio de colas y los pagos volvieron.', 2 * 86_400 + 3 * 3600 - 600)],
  },
  { id: 12, component: 'Web', status: 'resolved', started_at: ago(61 * 86_400), ended_at: ago(61 * 86_400 - 840), title: 'Web no respondió el 6/9 entre las 10:00 y las 10:14.', notes: [] },
]
