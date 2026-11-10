import type { ProjectSummary } from '@/features/projects/types'

export type ClientStatus = 'active' | 'inactive'

export const clientStatusLabels: Record<ClientStatus, string> = {
  active: 'Activo',
  inactive: 'Inactivo',
}

export interface Client {
  id: number
  legal_name: string
  trade_name: string | null
  display_name: string
  tax_id: string | null
  contact_name: string | null
  email: string | null
  phone: string | null
  notes: string | null
  status: ClientStatus
  /** Present in the list. */
  projects_count?: number
  /** Present in the client file (RF-02.02). */
  projects?: ProjectSummary[]
}

export interface ClientFilters {
  status: ClientStatus | ''
  search: string
  page: number
}

export interface SaveClientInput {
  legal_name: string
  trade_name: string | null
  tax_id: string | null
  contact_name: string | null
  email: string | null
  phone: string | null
  notes: string | null
}

/** Who may open /clientes (UI hint; ClientPolicy decides). */
export const CLIENTS_READ_PERMISSIONS = ['clients.manage', 'projects.manage'] as const
