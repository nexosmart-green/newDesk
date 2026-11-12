import { ArrowLeft, Clock } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { Tabs, type TabItem } from '@/components/ui/Tabs'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCurrentUser, useProfileTimeZone } from '@/features/auth/useAuth'
import { CategoryTag, LabelChips } from '@/features/taskCatalogs/LabelChip'
import { StartTimerButton } from '@/features/timer/StartTimerButton'
import { ApiError } from '@/lib/api'
import { formatInstant } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import { useTabParam } from '@/lib/useSearchParamPatch'
import { TaskAttachmentsTab } from './attachments/TaskAttachmentsTab'
import { TaskDue, TaskStatusLabel } from './TaskBits'
import { TaskComments } from './TaskComments'
import { TaskDetailTab } from './TaskDetailTab'
import { TaskHistoryTab } from './TaskHistoryTab'
import { TaskHoursTab } from './TaskHoursTab'
import { isAssignedTo, type Task } from './types'
import { useTask } from './useTasks'

type TaskTab = 'detalle' | 'comentarios' | 'archivos' | 'horas' | 'historial'

const allTabs: TabItem<TaskTab>[] = [
  { id: 'detalle', label: 'Detalle' },
  { id: 'comentarios', label: 'Comentarios' },
  { id: 'archivos', label: 'Archivos' },
  { id: 'horas', label: 'Horas' },
  { id: 'historial', label: 'Historial' },
]

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
        <Link to="/mis-tareas" className="text-sm text-accent">
          Volver a Mis tareas
        </Link>
      </section>
    )
  }
  if (!task.data) return <PageState isPending={task.isPending} error={task.error} shape="detail" onRetry={() => void task.refetch()} />
  return <TaskView task={task.data} />
}

function TaskView({ task }: { task: Task }) {
  const userId = useCurrentUser().data?.id
  const hasTime = useHasModule(MODULES.time)
  const mine = isAssignedTo(task, userId)
  const tabs = (hasTime ? allTabs : allTabs.filter((tab) => tab.id !== 'horas')).map((tab) =>
    tab.id === 'archivos' ? { ...tab, count: task.attachments_count } : tab,
  )
  const { selected, select } = useTabParam(
    tabs.map((tab) => tab.id),
    'detalle',
  )

  return (
    <section className="max-w-6xl space-y-5">
      {task.project && (
        <Link to={`/proyectos/${task.project.id}/tareas`} className="inline-flex items-center gap-1 text-sm text-accent hover:underline">
          <ArrowLeft aria-hidden="true" className="size-4" />
          {task.project.name}
        </Link>
      )}
      <TaskHeader task={task} showLogged={hasTime} timer={mine ? <StartTimerButton task={task} /> : null} />
      <Tabs label="Secciones de la tarea" tabs={tabs} selected={selected} onSelect={select}>
        {selected === 'detalle' && <TaskDetailTab task={task} />}
        {selected === 'comentarios' && <TaskComments code={task.code} projectId={task.project?.id} />}
        {selected === 'archivos' && <TaskAttachmentsTab code={task.code} />}
        {selected === 'horas' && <TaskHoursTab task={task} canLog={mine} />}
        {selected === 'historial' && <TaskHistoryTab code={task.code} />}
      </Tabs>
    </section>
  )
}

function TaskHeader({ task, showLogged, timer }: { task: Task; showLogged: boolean; timer: ReactNode }) {
  const timeZone = useProfileTimeZone()
  return (
    <header className="flex flex-col gap-4 sm:flex-row sm:items-start">
      <div className="min-w-0 flex-1 space-y-2">
        <p className="font-mono text-sm uppercase text-muted">{task.code}</p>
        <h1 className="text-xl font-semibold leading-snug tracking-tight text-balance">{task.title}</h1>
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
          <TaskStatusLabel status={task.status} />
          <TaskDue task={task} />
          <CategoryTag category={task.category} />
          <LabelChips labels={task.labels} />
        </div>
        {task.created_at && (
          <p className="text-xs text-muted">
            Creada {task.created_by ? `por ${task.created_by.full_name} ` : ''}el{' '}
            <time dateTime={task.created_at}>{formatInstant(task.created_at, timeZone)}</time>
          </p>
        )}
      </div>
      <div className="flex items-center gap-3">
        {showLogged && task.logged_seconds !== undefined && (
          <p className="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2">
            <Clock aria-hidden="true" className="size-4 text-accent" />
            <span className="sr-only">Horas registradas:</span>
            <span className="tabular text-sm font-semibold">{formatDuration(task.logged_seconds)}</span>
          </p>
        )}
        {timer}
      </div>
    </header>
  )
}
