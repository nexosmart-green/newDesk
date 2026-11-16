import type { ReactNode } from 'react'
import { Link } from 'react-router'
import type { DashboardTasks } from './types'

const plural = (count: number, one: string, many: string) => (count === 1 ? one : many)

function CountLink({ to, tone, children }: { to: string; tone?: 'danger' | 'warning'; children: ReactNode }) {
  const color = tone === 'danger' ? 'text-danger' : tone === 'warning' ? 'text-warning' : 'text-text'
  return (
    <Link to={to} className={`tabular font-semibold underline decoration-current/30 underline-offset-4 hover:decoration-current ${color}`}>
      {children}
    </Link>
  )
}

/** "Tenés 54 tareas pendientes, 38 vencidas y 2 que vencen hoy." */
function tasksSentence(tasks: DashboardTasks): ReactNode {
  if (tasks.open === 0) return 'No tenés tareas pendientes.'
  const open = (
    <CountLink to="/mis-tareas">
      {tasks.open} {plural(tasks.open, 'tarea pendiente', 'tareas pendientes')}
    </CountLink>
  )
  const overdue =
    tasks.overdue === 0 ? (
      'ninguna vencida'
    ) : (
      <CountLink to="/mis-tareas?vencidas=1" tone="danger">
        {tasks.overdue} {plural(tasks.overdue, 'vencida', 'vencidas')}
      </CountLink>
    )
  if (tasks.due_today === 0) {
    return (
      <>
        Tenés {open} y {overdue}.
      </>
    )
  }
  return (
    <>
      Tenés {open}, {overdue} y{' '}
      <CountLink to="/mis-tareas" tone="warning">
        {tasks.due_today} que {plural(tasks.due_today, 'vence', 'vencen')} hoy
      </CountLink>
      .
    </>
  )
}

/** The counters as one sentence, each figure a link to the list behind it. */
export function TaskCounters({ tasks, projects }: { tasks: DashboardTasks | null; projects: { active: number } | null }) {
  if (!tasks && !projects) return null

  return (
    <nav aria-label="Resumen de tareas">
      <p className="text-base leading-relaxed">
        {tasks && tasksSentence(tasks)}
        {tasks && projects && ' '}
        {projects && (
          <>
            Hay{' '}
            <CountLink to="/proyectos">
              {projects.active} {plural(projects.active, 'proyecto activo', 'proyectos activos')}
            </CountLink>
            .
          </>
        )}
      </p>
    </nav>
  )
}
