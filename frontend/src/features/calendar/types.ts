import type { Holiday, Shift } from '@/features/shifts/types'

export interface DueTask {
  code: string
  title: string
  due_on: string
}

/** RF-06.04 `GET /me/calendar`: shift times are company-local (`timezone`). */
export interface MyCalendar {
  timezone: string
  shifts: Shift[]
  tasks_due: DueTask[]
  holidays: Holiday[]
}

export interface CalendarDay {
  shifts: Shift[]
  tasks: DueTask[]
  holidays: Holiday[]
}

export interface HolidayInput {
  date: string
  name: string
  scope: Holiday['scope']
}

export const holidayScopeLabels: Record<Holiday['scope'], string> = { national: 'Nacional', company: 'De la empresa' }

export type CalendarView = 'semana' | 'mes'
