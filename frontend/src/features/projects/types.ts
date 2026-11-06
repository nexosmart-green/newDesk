import type { PersonSummary } from '@/features/people/types'

export type ProjectStatus = 'active' | 'paused' | 'finished' | 'cancelled'

export const projectStatusLabels: Record<ProjectStatus, string> = {
  active: 'Activo',
  paused: 'En pausa',
  finished: 'Finalizado',
  cancelled: 'Cancelado',
}

export const projectStatuses = Object.keys(projectStatusLabels) as ProjectStatus[]

/** RF-03.08: closed projects accept no new tasks nor timers. */
export function isClosedStatus(status: ProjectStatus): boolean {
  return status === 'finished' || status === 'cancelled'
}

export function isProjectStatus(value: unknown): value is ProjectStatus {
  return typeof value === 'string' && (projectStatuses as string[]).includes(value)
}

/** P-05: hard hour bag or internal (no cap). */
export type ProjectType = 'bag' | 'internal'

export const projectTypeLabels: Record<ProjectType, string> = {
  bag: 'Bolsa de horas',
  internal: 'Interno',
}

export interface ProjectSummary {
  id: number
  name: string
  code_prefix: string
  type: ProjectType
  status: ProjectStatus
  status_label: string
  client?: { id: number; name: string } | null
  leader?: { id: number; full_name: string } | null
  members_count?: number
  starts_on: string | null
  ends_on: string | null
}

export interface ProjectDetail extends ProjectSummary {
  description: string | null
  /** CA-03.01: once tasks exist the prefix is part of their codes and can't change. */
  prefix_locked: boolean
  members: PersonSummary[]
  can: { update: boolean; manage_members: boolean }
}

export interface ProjectFilters {
  client_id: number | null
  status: ProjectStatus | ''
  search: string
  page: number
}

export interface SaveProjectInput {
  name: string
  code_prefix: string
  type: ProjectType
  client_id: number | null
  description: string | null
  leader_user_id: number | null
  starts_on: string | null
  ends_on: string | null
}

export interface CreateProjectInput extends SaveProjectInput {
  member_ids: number[]
}

export type ActivitySource = 'web' | 'mcp' | 'system'

export interface ActivityEntry {
  id: number
  event: string
  actor: { id: number; full_name: string } | null
  source: ActivitySource
  token_name: string | null
  before: Record<string, unknown> | null
  after: Record<string, unknown> | null
  created_at: string
}
