import type { ReportDetail, ReportMessage, ReportRow, ReportStatus } from '../api/types'
import { initialMessages, initialReports, type ReportFixture } from './fixtures/reports'
import { mockMonitoringApi, reply, type Routes } from './mockApi'

// La cola de reportes del equipo simulada en memoria (D-049..D-051, D-064..D-066): listas, detalle con chat, estado y
// el roadmap de quien mira. Abrir un reporte lo marca leído. `routes` se puede cambiar a mitad del test.
export function mockReports(overrides: Routes = {}) {
  const state = { reports: initialReports(), messages: initialMessages(), seen: {} as Record<number, number>, roadmap: [] as number[], nextId: 900 }
  const find = (id: string | undefined) => state.reports.find((each) => each.id === Number(id))
  const notFound = reply({ status: 404, message: 'Reporte no encontrado.' })
  const unread = (report: ReportFixture) => (state.messages[report.id] ?? []).some((each) => each.author_kind === 'client' && each.id > (state.seen[report.id] ?? 0))
  const row = (report: ReportFixture): ReportRow => ({
    id: report.id, created_at: report.created_at, urgency: report.urgency, status: report.status, description: report.description.slice(0, 140),
    client_id: report.client_id, client_name: report.client_name, project_id: report.project_id, project_name: report.project_name,
    user_email: report.user_email, component_label: report.component_label, has_screenshot: report.has_screenshot, unread: unread(report),
  })
  const detail = (report: ReportFixture): ReportDetail => ({ ...report, unread: unread(report), messages: state.messages[report.id] ?? [] })
  const roadmap = () => state.roadmap.flatMap((id) => state.reports.filter((each) => each.id === id).map(row))

  const routes: Routes = {
    'GET portal-admin/reports': ({ query }) => {
      const status = query.get('status') as ReportStatus | null
      const rows = state.reports.filter((each) => !status || each.status === status).map(row)
      return reply({ data: rows, meta: { page: Number(query.get('page') ?? 1), per_page: 10, total: rows.length } })
    },
    'GET portal-admin/reports/attention': () => ({ count: state.reports.filter((each) => each.status === 'received' || unread(each)).length }),
    'GET portal-admin/reports/:id': ({ params }) => {
      const report = find(params.id)
      if (!report) return notFound
      state.seen[report.id] = state.messages[report.id]?.at(-1)?.id ?? 0
      return detail(report)
    },
    'PUT portal-admin/reports/:id': ({ params, body }) => {
      const report = find(params.id)
      if (!report) return notFound
      const status = (body as { status: ReportStatus }).status
      Object.assign(report, { status, resolved_at: status === 'resolved' ? new Date().toISOString() : null })
      return detail(report)
    },
    'POST portal-admin/reports/:id/messages': ({ params, body }) => {
      const report = find(params.id)
      if (!report || !(body instanceof FormData)) return notFound
      const images = body.getAll('images[]').map(() => `api/portal-admin/reports/${report.id}/attachments/${state.nextId++}`)
      const message: ReportMessage = {
        id: state.nextId++, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: String(body.get('body') ?? ''),
        created_at: new Date().toISOString(), images, internal: body.get('internal') === '1',
      }
      ;(state.messages[report.id] ??= []).push(message)
      return reply({ status: 201, data: message })
    },
    'GET portal-admin/roadmap': roadmap,
    'POST portal-admin/roadmap': ({ body }) => {
      const id = (body as { report_id: number }).report_id
      if (!state.roadmap.includes(id)) state.roadmap.push(id)
      return reply({ data: roadmap(), message: 'Sumado a tu roadmap.' })
    },
    'PUT portal-admin/roadmap': ({ body }) => {
      state.roadmap = (body as { report_ids: number[] }).report_ids
      return roadmap()
    },
    'DELETE portal-admin/roadmap/:id': ({ params }) => {
      state.roadmap = state.roadmap.filter((id) => id !== Number(params.id))
      return reply({ data: roadmap(), message: 'Quitado de tu roadmap.' })
    },
    'GET daily-report/status': { available: false, reason: 'Claude de root del server 5 no está conectado al panel (falta monitor-claude.socket).' },
    ...overrides,
  }
  return { state, routes, api: mockMonitoringApi(routes) }
}
