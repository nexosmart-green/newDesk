import type { HourBag } from '@/features/hours/types'
import type { Task, TaskStatus } from '@/features/tasks/types'
import type { DayClock, TimeEntry, TimerState } from '@/features/timer/types'
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
    hours: buildHourBag(),
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

export function buildHourBag(overrides: Partial<HourBag> = {}): HourBag {
  return {
    has_cap: true,
    allocated_seconds: 36000,
    consumed_seconds: 7200,
    running_seconds: 0,
    available_seconds: 28800,
    percent_consumed: 20,
    is_exhausted: false,
    ...overrides,
  }
}

export const taskStatuses: TaskStatus[] = [
  { id: 1, name: 'Pendiente', color: '#9aa3b2', position: 1, kind: 'open' },
  { id: 2, name: 'En curso', color: '#4f8cff', position: 2, kind: 'open' },
  { id: 3, name: 'Bloqueado', color: '#f5b942', position: 3, kind: 'blocked' },
  { id: 5, name: 'Completada', color: '#3ecf8e', position: 5, kind: 'closed' },
]

export function buildTask(overrides: Partial<Task> = {}): Task {
  return {
    id: 60,
    code: 'bhn-60',
    title: 'Arreglar el login',
    project: { id: 9, name: 'Bahía Web', code_prefix: 'bhn', hours_exhausted: false },
    status: { id: 1, name: 'Pendiente', color: '#9aa3b2', kind: 'open' },
    priority: 'medium',
    starts_on: null,
    due_on: null,
    is_overdue: false,
    estimate_seconds: null,
    completed_at: null,
    assignees: [{ id: 1, full_name: 'Ana García', avatar: null }],
    ...overrides,
  }
}

export const SERVER_TIME = '2026-11-06T15:00:00+00:00'

export function buildTimeEntry(overrides: Partial<TimeEntry> = {}): TimeEntry {
  return {
    id: 300,
    is_running: true,
    started_at: '2026-11-06T13:58:55+00:00',
    ended_at: null,
    elapsed_seconds: 3665,
    server_time: SERVER_TIME,
    source: 'timer',
    channel: 'web',
    comment: null,
    comment_pending: false,
    stop_reason: null,
    stop_reason_label: null,
    task: { id: 60, code: 'bhn-60', title: 'Arreglar el login' },
    project: { id: 9, name: 'Bahía Web' },
    user: { id: 1, full_name: 'Ana García' },
    ...overrides,
  }
}

export function buildTimerState(overrides: Partial<TimerState> = {}): TimerState {
  return { active: null, pending_comments: [], day_clock: null, server_time: SERVER_TIME, ...overrides }
}

/** Pending by default: 09:02:37 → 18:14:00 in Buenos Aires (UTC−3), 33 083 s on the clock. */
export function buildDayClock(overrides: Partial<DayClock> = {}): DayClock {
  return {
    id: 40,
    started_at: '2026-11-10T12:02:37+00:00',
    ended_at: '2026-11-10T21:14:00+00:00',
    cutoff_at: '2026-11-11T03:00:00+00:00',
    status: 'pending',
    elapsed_seconds: 33_083,
    server_time: SERVER_TIME,
    ...overrides,
  }
}
