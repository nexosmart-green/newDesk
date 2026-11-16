import { FileText, Pencil } from 'lucide-react'
import type { ReactNode } from 'react'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { MarkdownView } from '@/components/ui/Markdown'
import { useCan } from '@/features/auth/useAuth'
import { LabelChips } from '@/features/taskCatalogs/LabelChip'
import { formatDate } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import { useEditInPlace } from '@/lib/useEditInPlace'
import { TaskChecklist } from './checklist/TaskChecklist'
import { TaskAssigneesEditor } from './TaskAssigneesEditor'
import { TaskDetailsForm } from './TaskDetailsForm'
import { Panel } from './TaskPanel'
import { TaskStatusSelect } from './TaskStatusSelect'
import { taskPriorityLabels, type Task } from './types'
import { useTaskStatuses } from './useTasks'
import { useUpdateTaskStatus } from './useTaskStatusChange'

/**
 * Tab "Detalle": what the task is about on one surface (description, subtasks), and beside it a plain list
 * of its properties — status first, the people last. "Editar detalles" turns the description into the form in place.
 */
export function TaskDetailTab({ task }: { task: Task }) {
  const { containerRef, editing, start, stop } = useEditInPlace()

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
      <div ref={containerRef} className="min-w-0 space-y-8 rounded-lg border border-line bg-surface p-4 sm:p-6">
        {editing ? (
          <Panel title="Editar detalles">
            <TaskDetailsForm key={task.id} task={task} onDone={stop} />
          </Panel>
        ) : (
          <Panel
            title="Descripción"
            action={
              <Button variant="ghost" onClick={start} data-edit-trigger>
                <Pencil aria-hidden="true" className="size-4" />
                Editar detalles
              </Button>
            }
          >
            {task.description?.trim() ? (
              <MarkdownView source={task.description} />
            ) : (
              <EmptyState
                icon={FileText}
                bare
                action={
                  <Button variant="secondary" onClick={start} data-edit-trigger>
                    Agregar descripción
                  </Button>
                }
              >
                Sin descripción. Contá qué hay que hacer, con capturas si hace falta.
              </EmptyState>
            )}
          </Panel>
        )}
        <TaskChecklist task={task} />
      </div>
      <TaskProperties task={task} />
    </div>
  )
}

function Property({ term, children, stacked = false }: { term: string; children: ReactNode; stacked?: boolean }) {
  return (
    <div className={stacked ? 'space-y-2 py-3' : 'grid grid-cols-[6.5rem_minmax(0,1fr)] items-baseline gap-3 py-2'}>
      <dt className="text-muted">{term}</dt>
      <dd className="min-w-0">{children}</dd>
    </div>
  )
}

const none = (text: string) => <span className="text-muted">{text}</span>

/** Logged hours aren't repeated here: the page header shows them on every tab. */
function TaskProperties({ task }: { task: Task }) {
  return (
    <dl aria-label="Datos de la tarea" className="divide-y divide-line text-sm lg:pt-1">
      <Property term="Estado">
        <StatusControl task={task} />
      </Property>
      <Property term="Prioridad">{taskPriorityLabels[task.priority]}</Property>
      <Property term="Categoría">{task.category?.name ?? none('Sin categoría')}</Property>
      <Property term="Etiquetas">{task.labels && task.labels.length > 0 ? <LabelChips labels={task.labels} /> : none('Ninguna')}</Property>
      <Property term="Inicio">{task.starts_on ? formatDate(task.starts_on) : none('Sin fecha')}</Property>
      <Property term="Vencimiento">{task.due_on ? formatDate(task.due_on) : none('Sin fecha')}</Property>
      <Property term="Estimación">
        {task.estimate_seconds === null ? none('Sin estimar') : <span className="tabular">{formatDuration(task.estimate_seconds)}</span>}
      </Property>
      <Property term="Asignados" stacked>
        <Assignees task={task} />
      </Property>
    </dl>
  )
}

function Assignees({ task }: { task: Task }) {
  const canAssign = useCan('tasks.assign')
  const { containerRef, editing, start, stop } = useEditInPlace()
  const assignees = task.assignees ?? []
  if (editing) {
    return (
      <div ref={containerRef}>
        <TaskAssigneesEditor key={task.id} task={task} onDone={stop} />
      </div>
    )
  }

  return (
    <div ref={containerRef} className="space-y-2">
      {assignees.length === 0 ? (
        none('Sin asignar')
      ) : (
        <ul className="space-y-2">
          {assignees.map((assignee) => (
            <li key={assignee.id} className="flex items-center gap-2">
              <Avatar name={assignee.full_name} url={assignee.avatar?.avatar} size="xs" />
              {assignee.full_name}
            </li>
          ))}
        </ul>
      )}
      {canAssign && (
        <Button variant="link" className="text-sm" onClick={start} data-edit-trigger>
          Cambiar asignados
        </Button>
      )}
    </div>
  )
}

function StatusControl({ task }: { task: Task }) {
  const statuses = useTaskStatuses()
  const updateStatus = useUpdateTaskStatus()
  return (
    <div className="space-y-2">
      <TaskStatusSelect
        code={task.code}
        current={task.status}
        statuses={statuses.data ?? []}
        disabled={updateStatus.isPending || statuses.isPending}
        onChange={(statusId) => updateStatus.mutate({ code: task.code, statusId })}
      />
      <FieldErrorsAlert error={updateStatus.error} />
    </div>
  )
}
