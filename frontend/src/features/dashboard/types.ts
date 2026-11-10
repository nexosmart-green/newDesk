import type { Task } from '@/features/tasks/types'

export interface DashboardTasks {
  /** Open tasks assigned to me. */
  open: number
  overdue: number
  due_today: number
  /** Up to 5, open, mine, by due date ascending. */
  due_soon: Task[]
}

export interface DashboardWeek {
  /** Monday–Sunday in the person's timezone (`YYYY-MM-DD`). */
  from: string
  to: string
  total_seconds: number
  days: { date: string; seconds: number }[]
}

export interface BagRunningOut {
  id: number
  name: string
  code_prefix: string
  allocated_seconds: number
  consumed_seconds: number
}

/** `GET /me/dashboard`: a block the role can't see comes back `null` (module off or no permission). */
export interface Dashboard {
  tasks: DashboardTasks | null
  projects: { active: number } | null
  week: DashboardWeek | null
  bags_running_out: BagRunningOut[] | null
}
