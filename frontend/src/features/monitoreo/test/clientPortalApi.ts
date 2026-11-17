import axios from 'axios'
import type { CurrentUser } from '@/features/auth/types'
import type { ClientReport, ClientReportDetail, PortalCard, ReportCreated, ReportMessage, ReportUrgency, ResponseRange } from '../api/types'
import { partLabels } from '../lib/parts'
import { playinnLive, playinnMessages, playinnReports, portalProjects, responseTimes, type ClientReportFixture } from './fixtures/clientPortal'
import { mockMonitoringApi, reply, type Routes } from './mockApi'

export const MOCK_CODE = '123456'

// El portal del cliente simulado en memoria (`/api/v1/portal/<ruta>` y el /me de newDesk), como lo responde el back:
// cada cliente ve solo lo suyo y nunca las notas internas (CA-13.13, CA-13.18). `user` null = sin sesión (401).
// Abrir un reporte lo marca leído; escribir en uno resuelto lo reabre. `state.user` se puede cambiar a mitad del test.
export function mockClientPortal(user: CurrentUser | null, overrides: Routes = {}) {
  const state = {
    user, projects: portalProjects(), reports: playinnReports(), messages: playinnMessages(), seen: { 2: 31 } as Record<number, number>, nextId: 900,
    // El último reporte que llegó, con lo que mandó el navegador.
    created: null as (ClientReportFixture & { form: FormData }) | null,
  }
  const notFound = reply({ status: 404, message: 'No encontramos ese proyecto.' })
  const mine = (slug: string | undefined) => state.projects.find((each) => each.slug === slug && each.client_id === state.user?.client?.id)
  const report = (slug: string | undefined, id: string | undefined) => (mine(slug) ? state.reports.find((each) => each.slug === slug && each.id === Number(id)) : undefined)
  const unread = (item: ClientReportFixture) => (state.messages[item.id] ?? []).some((each) => !each.internal && each.author_kind === 'team' && each.id > (state.seen[item.id] ?? 0))
  const view = (item: ClientReportFixture): ClientReport => {
    const { slug: _slug, ...fields } = item
    return { ...fields, unread: unread(item) }
  }
  const messages = (id: number): ReportMessage[] =>
    (state.messages[id] ?? [])
      .filter((each) => !each.internal)
      .map(({ internal: _internal, ...each }) => ({ ...each, author: each.author_kind === 'team' ? 'Equipo nexosmart' : each.author }))
  const card = ({ slug, name, primary_color, logo_url, status, phrase, incident }: PortalCard): PortalCard => ({ slug, name, primary_color, logo_url, status, phrase, incident })

  const routes: Routes = {
    'GET me': () => state.user ?? reply({ status: 401, message: 'No autenticado.' }),
    'POST auth/logout': null,
    'POST portal/auth/request-code': reply({ message: 'Si el mail está habilitado, te enviamos un código.' }),
    'POST portal/auth/verify-code': ({ body }) =>
      (body as { code: string }).code === MOCK_CODE ? null : reply({ status: 422, message: 'Datos inválidos.', errors: { code: ['Código inválido o vencido.'] } }),
    'GET portal/projects': () => state.projects.filter((each) => each.client_id === state.user?.client?.id).map(card),
    'GET portal/projects/:slug': ({ params }) => {
      const project = mine(params.slug)
      if (!project) return notFound
      const { client_id: _client, latency: _latency, ...status } = project
      return status
    },
    'GET portal/projects/:slug/response-times': ({ params, query }) => {
      const project = mine(params.slug)
      return project ? responseTimes(project.latency, (query.get('range') ?? '24h') as ResponseRange) : notFound
    },
    'GET portal/projects/:slug/reports': ({ params }) => (mine(params.slug) ? state.reports.filter((each) => each.slug === params.slug).map(view) : notFound),
    'GET portal/projects/:slug/reports/:id': ({ params }): ClientReportDetail | ReturnType<typeof reply> => {
      const item = report(params.slug, params.id)
      if (!item) return reply({ status: 404, message: 'No encontramos ese reporte.' })
      state.seen[item.id] = state.messages[item.id]?.at(-1)?.id ?? 0
      return { report: view(item), messages: messages(item.id) }
    },
    'POST portal/projects/:slug/reports': ({ params, body }): ReportCreated | ReturnType<typeof reply> => {
      const project = mine(params.slug)
      if (!project || !(body instanceof FormData)) return notFound
      const now = new Date().toISOString()
      const id = state.nextId++
      const part = String(body.get('part') ?? '') as keyof typeof partLabels | ''
      const created: ClientReportFixture = {
        id, slug: project.slug, created_at: now, updated_at: now, resolved_at: null, urgency: String(body.get('urgency')) as ReportUrgency,
        status: 'received', description: String(body.get('description')), component_label: part ? partLabels[part] : null,
        screenshot_url: body.has('screenshot') ? `api/portal/projects/${project.slug}/reports/${id}/screenshot` : null,
      }
      state.reports.unshift(created)
      state.created = { ...created, form: body }
      return reply({ status: 201, data: { report: view(created), live: playinnLive } })
    },
    'POST portal/projects/:slug/reports/:id/messages': ({ params, body }) => {
      const item = report(params.slug, params.id)
      if (!item || !(body instanceof FormData)) return reply({ status: 404, message: 'No encontramos ese reporte.' })
      const images = body.getAll('images[]').map(() => `api/portal/projects/${item.slug}/reports/${item.id}/attachments/${state.nextId++}`)
      const message: ReportMessage = { id: state.nextId++, author_kind: 'client', author: state.user?.email ?? '', body: String(body.get('body') ?? ''), created_at: new Date().toISOString(), images, internal: false }
      ;(state.messages[item.id] ??= []).push(message)
      if (item.status === 'resolved') Object.assign(item, { status: 'in_review', resolved_at: null })
      return reply({ status: 201, data: messages(item.id).at(-1) })
    },
    ...overrides,
  }
  // La cookie XSRF de Sanctum (ensureCsrfCookie) va por el axios global, no por la instancia de la API.
  const csrf = vi.spyOn(axios, 'get').mockResolvedValue({ status: 204, data: null })
  return { state, routes, csrf, api: mockMonitoringApi(routes) }
}
