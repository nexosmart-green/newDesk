import type { Client } from '@/features/clients/types'
import type { PersonProfile, PersonSummary } from '@/features/people/types'
import type { ProjectDetail } from '@/features/projects/types'
import type { Paginated } from '@/lib/api'

export function page<T>(data: T[]): Paginated<T> {
  return { data, meta: { current_page: 1, last_page: 1, per_page: 50, total: data.length } }
}

export function buildPerson(overrides: Partial<PersonSummary> = {}): PersonSummary {
  const first = overrides.first_name ?? 'Ana'
  const last = overrides.last_name ?? 'García'
  return {
    id: 1,
    first_name: first,
    last_name: last,
    full_name: `${first} ${last}`,
    email: `${first.toLowerCase()}@nexosmart.com`,
    status: 'active',
    avatar: null,
    job_title: null,
    area: null,
    ...overrides,
  }
}

export function buildProfile(overrides: Partial<PersonProfile> = {}): PersonProfile {
  return {
    ...buildPerson(),
    skills: [],
    employee_code: null,
    employment_type: null,
    reports_to: null,
    phone: null,
    about: null,
    hired_at: null,
    left_at: null,
    probation_ends_at: null,
    locale: 'es',
    timezone: null,
    ...overrides,
  }
}

export function buildProject(overrides: Partial<ProjectDetail> = {}): ProjectDetail {
  return {
    id: 9,
    name: 'Bahía Web',
    code_prefix: 'bhn',
    type: 'bag',
    status: 'active',
    status_label: 'Activo',
    client: { id: 5, name: 'Bahía' },
    leader: { id: 1, full_name: 'Ana García' },
    members_count: 0,
    starts_on: null,
    ends_on: null,
    description: null,
    prefix_locked: false,
    members: [],
    can: { update: true, manage_members: true },
    ...overrides,
  }
}

export function buildClient(overrides: Partial<Client> = {}): Client {
  return {
    id: 5,
    legal_name: 'Bahía SRL',
    trade_name: 'Bahía',
    display_name: 'Bahía',
    tax_id: null,
    contact_name: null,
    email: null,
    phone: null,
    notes: null,
    status: 'active',
    ...overrides,
  }
}
