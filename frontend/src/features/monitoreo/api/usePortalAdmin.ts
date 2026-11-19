import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { monitoringApi as api } from './monitoringApi'
import { keys } from './useMonitoring'
import type {
  ClientDetail, ClientRow, DiscordChannel, DiscordMember, FolderOption, Incident, PortalStatus, ProjectAdmin, ProjectRow, ProjectVulns, ResponseRange,
  ResponseTimes, ServerTargets, Suggestion, User,
} from './types'

// Clientes y proyectos (el "portal-admin" de monitoreo). Las escrituras están en useProjectMutations.

export function useAllProjects() {
  return useQuery({ queryKey: keys.allProjects, queryFn: () => api.get<ProjectRow[]>('portal-admin/projects') })
}

export function useClients() {
  return useQuery({ queryKey: keys.clients, queryFn: () => api.get<ClientRow[]>('portal-admin/clients') })
}

export function useClient(id: number) {
  return useQuery({ queryKey: keys.client(id), queryFn: () => api.get<ClientDetail>(`portal-admin/clients/${id}`) })
}

export function useAdminProject(id: number) {
  return useQuery({ queryKey: keys.adminProject(id), queryFn: () => api.get<ProjectAdmin>(`portal-admin/projects/${id}`) })
}

export function useProjectPreview(id: number) {
  return useQuery({ queryKey: keys.preview(id), queryFn: () => api.get<PortalStatus>(`portal-admin/projects/${id}/preview`) })
}

// Tiempos de respuesta del sitio y la API. `path` es la base del proyecto: la del cliente o la del equipo (vista previa).
export function useResponseTimes(path: string, range: ResponseRange) {
  return useQuery({
    queryKey: keys.responseTimes(path, range),
    queryFn: () => api.get<ResponseTimes>(`${path}/response-times?range=${range}`),
    placeholderData: keepPreviousData,
    refetchInterval: range === '24h' ? 60_000 : 300_000,
  })
}

// Incidentes de los últimos 90 días con el autor de cada nota (equipo).
export function useProjectIncidents(id: number) {
  return useQuery({ queryKey: keys.incidents(id), queryFn: () => api.get<Incident[]>(`portal-admin/projects/${id}/incidents`) })
}

// Todas las carpetas del inventario, con de qué proyecto de cliente es cada una (D-044).
export function useFolderOptions() {
  return useQuery({ queryKey: keys.folders, queryFn: () => api.get<FolderOption[]>('portal-admin/folders'), staleTime: 60_000 })
}

export function useSuggestions(id: number) {
  return useQuery({ queryKey: keys.suggestions(id), queryFn: () => api.get<Suggestion[]>(`portal-admin/projects/${id}/suggestions`) })
}

// Vulnerabilidades de dependencias de las carpetas del proyecto (D-053). El scan corre una vez por día.
export function useProjectVulnerabilities(id: number) {
  return useQuery({ queryKey: keys.vulnerabilities(id), queryFn: () => api.get<ProjectVulns>(`portal-admin/projects/${id}/vulnerabilities`) })
}

export function useTargets() {
  return useQuery({ queryKey: keys.targets, queryFn: () => api.get<ServerTargets[]>('portal-admin/targets'), staleTime: 5 * 60_000 })
}

// Los usuarios del equipo, para elegir los desarrolladores de un proyecto (solo admin).
// ponytail: primera página de usuarios; el equipo son pocas personas.
// The back pages the team (20, or 10 a page from 20 people on): every page, so nobody goes missing from a picker.
async function wholeTeam(): Promise<User[]> {
  const users: User[] = []
  for (let page = 1; ; page++) {
    const { data, meta } = await api.getEnvelope<User[]>(`users?page=${page}`)
    users.push(...data)
    if (!meta || data.length === 0 || page * meta.per_page >= meta.total) return users
  }
}

export function useTeamUsers() {
  return useQuery({
    queryKey: keys.teamUsers,
    queryFn: wholeTeam,
    select: (users) => users.filter((user) => !user.client && user.active),
  })
}

// La gente del servidor, para elegir la cuenta de cada uno. Sin bot, 503 (como los canales).
export function useDiscordMembers() {
  return useQuery({ queryKey: keys.discordMembers, queryFn: () => api.get<DiscordMember[]>('discord/members'), staleTime: 60_000, retry: false })
}

// Lo que ve el bot. Sin bot configurado el back responde 503: no tiene sentido reintentar.
export function useDiscordChannels() {
  return useQuery({ queryKey: keys.discordChannels, queryFn: () => api.get<DiscordChannel[]>('discord/channels'), staleTime: 60_000, retry: false })
}
