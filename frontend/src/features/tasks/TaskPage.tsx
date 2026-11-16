import { ArrowRightLeft, Clock, Ellipsis } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { BackLink } from '@/components/ui/BackLink'
import { Menu } from '@/components/ui/Menu'
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
import { MoveTaskDialog } from './MoveTaskDialog'
import { TaskCode } from './TaskCode'
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
        <h1 className="text-xl font-semibold leading-tight tracking-tight">Tarea no encontrada</h1>
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
  const [moving, setMoving] = useState(false)
  const tabs = (hasTime ? allTabs : allTabs.filter((tab) => tab.id !== 'horas')).map((tab) =>
    tab.id === 'archivos' ? { ...tab, count: task.attachments_count } : tab,
  )
  const { selected, select } = useTabParam(
    tabs.map((tab) => tab.id),
    'detalle',
  )

  return (
    <section className="max-w-6xl space-y-6">
      <div className="space-y-2">
        {task.project && <BackLink to={`/proyectos/${task.project.id}/tareas`}>{task.project.name}</BackLink>}
        <TaskHeader
          task={task}
          showLogged={hasTime}
          actions={
            <>
              {mine && <StartTimerButton task={task} />}
              <Menu
                label="Más acciones"
                trigger={<Ellipsis aria-hidden="true" className="size-5" />}
                triggerClassName="grid size-9 place-items-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-text coarse:size-11"
                items={[{ label: 'Mover a otro proyecto', icon: ArrowRightLeft, onSelect: () => setMoving(true) }]}
              />
            </>
          }
        />
      </div>
      <Tabs label="Secciones de la tarea" tabs={tabs} selected={selected} onSelect={select}>
        {selected === 'detalle' && <TaskDetailTab task={task} />}
        {selected === 'comentarios' && <TaskComments code={task.code} projectId={task.project?.id} />}
        {selected === 'archivos' && <TaskAttachmentsTab code={task.code} />}
        {selected === 'horas' && <TaskHoursTab task={task} canLog={mine} />}
        {selected === 'historial' && <TaskHistoryTab code={task.code} />}
      </Tabs>
      {moving && <MoveTaskDialog task={task} onClose={() => setMoving(false)} />}
    </section>
  )
}

function TaskHeader({ task, showLogged, actions }: { task: Task; showLogged: boolean; actions: ReactNode }) {
  const timeZone = useProfileTimeZone()
  return (
    <header className="flex flex-col gap-4 sm:flex-row sm:items-start">
      <div className="min-w-0 flex-1 space-y-2">
        <h1 className="text-xl font-semibold leading-snug tracking-tight text-balance">
          <TaskCode code={task.code} className="mr-2 align-[0.1em]" />
          {task.title}
        </h1>
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5">
          <TaskStatusLabel status={task.status} />
          <TaskDue task={task} />
          <CategoryTag category={task.category} />
          <LabelChips labels={task.labels} />
          {showLogged && task.logged_seconds !== undefined && (
            <span className="inline-flex items-center gap-1 text-xs text-muted">
              <Clock aria-hidden="true" className="size-3.5" strokeWidth={1.75} />
              <span className="tabular font-medium text-text">{formatDuration(task.logged_seconds)}</span> registradas
            </span>
          )}
        </div>
        {task.created_at && (
          <p className="text-xs text-muted">
            Creada {task.created_by ? `por ${task.created_by.full_name} ` : ''}el{' '}
            <time dateTime={task.created_at}>{formatInstant(task.created_at, timeZone)}</time>
          </p>
        )}
      </div>
      <div className="flex shrink-0 items-center gap-2">{actions}</div>
    </header>
  )
}
