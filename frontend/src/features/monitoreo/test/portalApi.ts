import type { ClientDetail, ComponentInput, DevelopersInput, FolderRef, Incident, PortalStatus, ProjectInput } from '../api/types'
import {
  clients, component, discordChannels, folderInventory, initialProjects, playinnIncidents, playinnSuggestions, projectRow, team, vulnsFor,
} from './fixtures/portal'
import { mockMonitoringApi, reply, type Routes } from './mockApi'

// El "portal-admin" de monitoreo simulado en memoria: lo que escribe un test se ve en el refetch, como con el back.
// `overrides` reemplaza rutas puntuales (un error, otra respuesta); `routes` se puede cambiar a mitad del test.
export function mockPortalAdmin(overrides: Routes = {}) {
  const state = { projects: initialProjects(), channels: discordChannels(), incidents: { 1: playinnIncidents() } as Record<number, Incident[]>, nextId: 200 }
  const find = (id: string | undefined) => state.projects.find((each) => each.id === Number(id))
  const notFound = reply({ status: 404, message: 'Proyecto no encontrado.' })
  const inventory = () =>
    folderInventory.map((folder) => {
      const owner = state.projects.find((each) => each.folders.some((linked) => linked.server_id === folder.server_id && linked.path === folder.path))
      return { ...folder, linked: owner ? { project_id: owner.id, project_name: owner.name, client_id: owner.client_id, client_name: owner.client_name } : null }
    })
  const developers = (input: DevelopersInput) => [
    ...(input.lead_user_id ? [{ user_id: input.lead_user_id, email: team.find((user) => user.id === input.lead_user_id)?.email ?? '', role: 'lead' as const }] : []),
    ...input.secondary_user_ids.map((id) => ({ user_id: id, email: team.find((user) => user.id === id)?.email ?? '', role: 'secondary' as const })),
  ]

  const routes: Routes = {
    'GET portal-admin/projects': () => state.projects.map(projectRow),
    'GET portal-admin/projects/:id': ({ params }) => find(params.id) ?? notFound,
    'PUT portal-admin/projects/:id': ({ params, body }) => {
      const item = find(params.id)
      if (!item) return notFound
      const { errors_token: _token, developers: devs, create_discord_channel: createChannel, ...input } = body as ProjectInput
      let channel = input.discord_channel_id ?? null
      // The bot creates the channel named after the project, as the backend does.
      if (createChannel) {
        channel = `156000000000000${String(item.id).padStart(4, '0')}`
        state.channels.push({ id: channel, name: item.name.toLowerCase().replace(/\s+/g, '-'), kind: 'channel', category: 'Equipo BBG1', can_send: true })
      }
      Object.assign(item, input, { discord_channel_id: channel }, devs ? { developers: developers(devs) } : {})
      return reply({ data: item, message: 'Proyecto guardado.' })
    },
    'GET portal-admin/clients/:id': ({ params }): ClientDetail | ReturnType<typeof reply> => {
      const client = clients.find((each) => each.id === Number(params.id))
      if (!client) return notFound
      return { client, users: [], projects: state.projects.filter((each) => each.client_id === client.id).map(projectRow) }
    },
    'GET discord/channels': () => state.channels,
    'POST portal-admin/projects/:id/discord-test': reply({ message: 'Mensaje de prueba enviado.' }),
    'GET users': team,
    'GET portal-admin/folders': inventory,
    'PUT portal-admin/projects/:id/folders': ({ params, body }) => {
      const item = find(params.id)
      if (!item) return notFound
      const refs = (body as { folders: FolderRef[] }).folders
      item.folders = refs.flatMap((ref) => {
        const folder = folderInventory.find((each) => each.server_id === ref.server_id && each.path === ref.path)
        const kept = item.folders.find((each) => each.server_id === ref.server_id && each.path === ref.path)
        return kept ?? (folder ? [{ server_id: folder.server_id, server_name: folder.server_name, path: folder.path, name: folder.name, types: [folder.type], last_update_at: null }] : [])
      })
      return item
    },
    'GET portal-admin/projects/:id/suggestions': ({ params }) => {
      const item = find(params.id)
      if (!item) return notFound
      return playinnSuggestions.map((each) => ({
        ...each, exists: item.components.some((other) => other.kind === each.kind && (each.url ? other.url === each.url : other.target === each.target)),
      }))
    },
    'POST portal-admin/projects/:id/components': ({ params, body }) => {
      const item = find(params.id)
      if (!item) return notFound
      const input = body as ComponentInput
      const created = component(item.id, state.nextId++, { ...input, server_name: input.server_id ? 'clientes' : null, status: 'unknown' })
      item.components.push(created)
      return reply({ status: 201, data: created })
    },
    'POST portal-admin/components/:id/pagespeed': reply({ status: 202 }),
    'GET portal-admin/targets': [],
    'GET portal-admin/projects/:id/vulnerabilities': ({ params }) => {
      const item = find(params.id)
      return item ? vulnsFor(item) : notFound
    },
    'GET portal-admin/projects/:id/incidents': ({ params }) => state.incidents[Number(params.id)] ?? [],
    'POST portal-admin/incidents/:id/notes': ({ params, body }) => {
      const incident = Object.values(state.incidents).flat().find((each) => each.id === Number(params.id))
      const note = { id: state.nextId++, body: (body as { body: string }).body, created_at: new Date().toISOString(), author: 'teamviolet@nexosmart.com.ar' }
      incident?.notes.push(note)
      return reply({ status: 201, data: note })
    },
    'DELETE portal-admin/incident-notes/:id': ({ params }) => {
      for (const incident of Object.values(state.incidents).flat()) incident.notes = incident.notes.filter((each) => each.id !== Number(params.id))
      return null
    },
    'GET portal-admin/projects/:id/preview': ({ params }): PortalStatus | ReturnType<typeof reply> => {
      const item = find(params.id)
      if (!item) return notFound
      // Lo que ve el cliente: las notas sin autor.
      const incidents = (state.incidents[item.id] ?? []).map((each) => ({ ...each, notes: each.notes.map(({ author: _author, ...rest }) => rest) }))
      return {
        slug: item.slug, name: item.name, primary_color: item.primary_color, logo_url: null, status: item.status, phrase: item.phrase, incident: false,
        client_name: item.client_name, checked_at: null, components: [], uptime: { days: [], month_pct: null, range_pct: null }, ssl: null, domain: null,
        backup: null, last_update: null, incidents, pagespeed: [],
      }
    },
    'GET portal-admin/projects/:id/response-times': ({ query }) => ({ range: query.get('range') ?? '24h', resolution: '5m', series: [] }),
    ...overrides,
  }
  return { state, routes, api: mockMonitoringApi(routes) }
}
