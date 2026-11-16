import type { Holiday, WorkMode } from '@/features/shifts/types'

export interface TeamPerson {
  id: number
  full_name: string
  /** Sizes → URL (e.g. `sm`, `md`); null = initials. */
  avatar: Record<string, string> | null
  job_title: string | null
}

/** Times are company-local (`TeamCalendar.timezone`); a shift ending before it starts crosses midnight. */
export interface TeamShift {
  id: number
  user_id: number
  starts_at: string
  ends_at: string
  mode: WorkMode
  template_name: string | null
  color: string | null
}

export interface TeamDay {
  date: string
  holiday: Pick<Holiday, 'name' | 'scope'> | null
  shifts: TeamShift[]
}

/** RF-06.05 `GET /team-calendar`: managers see everyone; leaders, themselves and their reports. */
export interface TeamCalendar {
  timezone: string
  people: TeamPerson[]
  days: TeamDay[]
}
