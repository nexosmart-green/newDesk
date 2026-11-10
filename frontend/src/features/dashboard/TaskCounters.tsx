import { Link } from 'react-router'
import type { DashboardTasks } from './types'

interface Counter {
  label: string
  value: number
  to: string
  /** Overdue reads in danger only when there is something overdue (the word says it too). */
  tone?: 'danger' | 'warning'
}

function countersOf(tasks: DashboardTasks | null, projects: { active: number } | null): Counter[] {
  const counters: Counter[] = []
  if (tasks) {
    counters.push(
      { label: 'Pendientes', value: tasks.open, to: '/mis-tareas' },
      { label: 'Vencidas', value: tasks.overdue, to: '/mis-tareas?vencidas=1', tone: tasks.overdue > 0 ? 'danger' : undefined },
      { label: 'Vencen hoy', value: tasks.due_today, to: '/mis-tareas', tone: tasks.due_today > 0 ? 'warning' : undefined },
    )
  }
  if (projects) counters.push({ label: 'Proyectos activos', value: projects.active, to: '/proyectos' })
  return counters
}

const toneClass = { danger: 'text-danger', warning: 'text-warning' } as const

/** One segmented strip, not a card per number: each segment opens the list behind it. */
export function TaskCounters({ tasks, projects }: { tasks: DashboardTasks | null; projects: { active: number } | null }) {
  const counters = countersOf(tasks, projects)
  if (counters.length === 0) return null

  return (
    <nav aria-label="Resumen de tareas" className="overflow-hidden rounded-xl border border-border bg-surface">
      <ul className="grid grid-cols-2 divide-border max-sm:[&>li:nth-child(n+3)]:border-t sm:flex sm:divide-x">
        {counters.map((counter, index) => (
          <li key={counter.label} className={`sm:flex-1 ${index % 2 === 1 ? 'max-sm:border-l max-sm:border-border' : ''}`}>
            <Link
              to={counter.to}
              className="flex h-full flex-col gap-0.5 px-4 py-3 transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
            >
              <span className={`tabular text-2xl font-semibold tracking-tight ${counter.tone ? toneClass[counter.tone] : ''}`}>
                {counter.value}
              </span>
              <span className="text-sm text-muted">{counter.label}</span>
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  )
}
