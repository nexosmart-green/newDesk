import type { CatalogItem } from '@/features/catalogs/types'

type PersonStatus = 'invited' | 'active' | 'inactive'

export interface AvatarUrls {
  /** 64px */
  avatar: string
  /** 256px */
  profile: string
}

export interface SkillLevel {
  id: number
  name: string
  level: number
}

/** Public card (directory, members, assignees). No sensitive data. */
export interface PersonSummary {
  id: number
  first_name: string
  last_name: string
  full_name: string
  email: string
  status: PersonStatus
  avatar: AvatarUrls | null
  job_title: CatalogItem | null
  area: CatalogItem | null
  /** Only present when loaded (profile, or the directory filtered by skill). */
  skills?: SkillLevel[]
}

/** RNF-06: present only for the person themself and `users.manage`. */
export interface SensitiveProfileFields {
  address: string | null
  birth_date: string | null
  gender: string | null
  cuil: string | null
  emergency_contact: string | null
}

export interface PersonProfile extends PersonSummary, Partial<SensitiveProfileFields> {
  employee_code: string | null
  employment_type: CatalogItem | null
  reports_to: { id: number; full_name: string } | null
  phone: string | null
  about: string | null
  hired_at: string | null
  left_at: string | null
  probation_ends_at: string | null
  locale: string
  timezone: string | null
}

export function hasSensitiveFields(profile: PersonProfile): boolean {
  return 'cuil' in profile
}

export type Locale = 'es' | 'en'

export interface UpdateOwnProfileInput {
  phone: string | null
  address: string | null
  about: string | null
  locale: Locale
  timezone: string | null
}

export interface UpdateEmployeeInput {
  [field: string]: string | number | null
}

export interface DirectoryFilters {
  search: string
  area_id: number | null
  skill_id: number | null
  min_level: number | null
  include_inactive: boolean
  page: number
}

export const emptyDirectoryFilters: DirectoryFilters = {
  search: '',
  area_id: null,
  skill_id: null,
  min_level: null,
  include_inactive: false,
  page: 1,
}

export interface HourlyRate {
  id: number
  amount_cents: number
  currency: string
  valid_from: string
  valid_to: string | null
}

export interface AddRateInput {
  amount_cents: number
  valid_from: string
}
