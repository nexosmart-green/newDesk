import type { ReactNode } from 'react'
import { Link, useLocation, useParams } from 'react-router'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { PageState } from '@/components/ui/PageState'
import { useCan, useCurrentUser } from '@/features/auth/useAuth'
import { useProject } from '@/features/projects/useProjects'
import { StartTimerButton } from '@/features/timer/StartTimerButton'
import { ApiError } from '@/lib/api'
import { ManualTimeForm } from './ManualTimeForm'
import { MoveTaskPanel } from './MoveTaskPanel'
import { TaskDue, TaskStatusLabel } from './TaskBits'
import { TaskAssigneesEditor } from './TaskAssigneesEditor'
import { TaskComments } from './TaskComments'
import { TaskDetailsForm } from './TaskDetailsForm'
import { TaskStatusSelect } from './TaskStatusSelect'
import { isAssignedTo, type Task } from './types'
import { useTask, useTaskStatuses, useUpdateTaskStatus } from './useTasks'

function Panel({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-3 rounded-lg border border-border bg-surface p-4">
      <h2 className="font-semibold">{title}</h2>
      {children}
    </section>
  )
}

function StatusPanel({ task }: { task: Task }) {
  const statuses = useTaskStatuses()
  const updateStatus = useUpdateTaskStatus()
  return (
    <div className="space-y-2">
      <TaskStatusSelect
        code={task.code}
        statusId={task.status?.id}
        statuses={statuses.data ?? []}
        disabled={updateStatus.isPending}
        onChange={(statusId) => updateStatus.mutate({ code: task.code, statusId })}
      />
      <ErrorAlert error={updateStatus.error} showFieldErrors />
    </div>
  )
}

/** `/tareas/:code` — the page notifications and the header timer link to. */
export function TaskPage() {
  const code = useParams().code ?? ''
  const task = useTask(code)

  // Tasks inherit project visibility: outsiders get 404 (CA-03.03).
  if (task.error instanceof ApiError && task.error.status === 404) {
    return (
      <section className="space-y-2">
        <h1 className="text-lg font-semibold">Tarea no encontrada</h1>
        <p className="text-sm text-muted">No existe o no sos miembro del proyecto.</p>
        <Link to="/" className="text-sm text-accent">
          Volver a Mis tareas
        </Link>
      </section>
    )
  }
  if (!task.data) return <PageState isPending={task.isPending} error={task.error} />
  return <TaskView task={task.data} projectId={task.data.project?.id ?? 0} />
}

function TaskView({ task: current, projectId }: { task: Task; projectId: number }) {
  const userId = useCurrentUser().data?.id
  const canAssign = useCan('tasks.assign')
  const project = useProject(projectId)
  const members = project.data?.members ?? []
  const mine = isAssignedTo(current, userId)
  const notice = (useLocation().state as { notice?: string } | null)?.notice

  return (
    <section className="max-w-3xl space-y-4">
      {current.project && (
        <Link to={`/proyectos/${current.project.id}/tareas`} className="text-sm text-accent">
          ← {current.project.name}
        </Link>
      )}
      <SuccessNotice message={notice} />
      <header className="flex flex-wrap items-start gap-3">
        <div className="min-w-0 flex-1 space-y-1">
          <p className="font-mono text-sm uppercase text-muted">{current.code}</p>
          <h1 className="text-lg font-semibold">{current.title}</h1>
          <div className="flex flex-wrap items-center gap-3">
            <TaskStatusLabel status={current.status} />
            <TaskDue task={current} />
          </div>
        </div>
        {mine && <StartTimerButton task={current} />}
      </header>
      <Panel title="Estado">
        <StatusPanel task={current} />
      </Panel>
      <Panel title="Detalles">
        <TaskDetailsForm key={current.id} task={current} />
      </Panel>
      <Panel title="Asignados">
        {canAssign && project.data ? (
          <TaskAssigneesEditor key={current.id} task={current} members={members} />
        ) : (
          <p className="text-sm">{(current.assignees ?? []).map((assignee) => assignee.full_name).join(', ') || 'Sin asignar'}</p>
        )}
      </Panel>
      {mine && (
        <Panel title="Cargar horas">
          <ManualTimeForm taskCode={current.code} projectId={current.project?.id} />
        </Panel>
      )}
      <TaskComments code={current.code} members={members} />
      <Panel title="Mover a otro proyecto">
        <MoveTaskPanel key={current.id} task={current} />
      </Panel>
    </section>
  )
}
