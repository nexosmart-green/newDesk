import { Pencil } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { MarkdownView } from '@/components/ui/Markdown'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { useCan } from '@/features/auth/useAuth'
import type { PersonSummary } from '@/features/people/types'
import { LabelChips } from '@/features/taskCatalogs/LabelChip'
import { formatDate } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import { TaskChecklist } from './checklist/TaskChecklist'
import { MoveTaskPanel } from './MoveTaskPanel'
import { TaskAssigneesEditor } from './TaskAssigneesEditor'
import { TaskDetailsForm } from './TaskDetailsForm'
import { Panel } from './TaskPanel'
import { TaskStatusSelect } from './TaskStatusSelect'
import { taskPriorityLabels, type Task } from './types'
import { useTaskStatuses, useUpdateTaskStatus } from './useTasks'

interface TaskDetailTabProps {
  task: Task
  members: PersonSummary[]
  membersLoaded: boolean
}

/** Tab "Detalle": the description reads as formatted text; "Editar detalles" turns it into the form in place. */
export function TaskDetailTab({ task, members, membersLoaded }: TaskDetailTabProps) {
  const [editing, setEditing] = useState(false)
  const canAssign = useCan('tasks.assign')

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_19rem] lg:items-start">
      <div className="min-w-0 space-y-4">
        {editing ? (
          <Panel title="Editar detalles">
            <TaskDetailsForm key={task.id} task={task} onDone={() => setEditing(false)} />
          </Panel>
        ) : (
          <Panel
            title="Descripción"
            action={
              <Button variant="ghost" onClick={() => setEditing(true)}>
                <Pencil aria-hidden="true" className="size-4" />
                Editar detalles
              </Button>
            }
          >
            {task.description?.trim() ? (
              <MarkdownView source={task.description} />
            ) : (
              <div className="flex flex-col items-start gap-2 rounded-lg border border-dashed border-border p-4">
                <p className="text-sm text-muted">Sin descripción. Contá qué hay que hacer, con capturas si hace falta.</p>
                <Button variant="secondary" onClick={() => setEditing(true)}>
                  Agregar descripción
                </Button>
              </div>
            )}
          </Panel>
        )}
        <TaskChecklist task={task} />
        <Panel title="Mover a otro proyecto">
          <MoveTaskPanel key={task.id} task={task} />
        </Panel>
      </div>
      <aside className="space-y-4" aria-label="Datos de la tarea">
        <Panel title="Estado">
          <StatusControl task={task} />
        </Panel>
        <Panel title="Datos">
          <TaskFacts task={task} />
        </Panel>
        <Panel title="Asignados">
          {canAssign && membersLoaded ? (
            <TaskAssigneesEditor key={task.id} task={task} members={members} />
          ) : (
            <p className="text-sm">{(task.assignees ?? []).map((assignee) => assignee.full_name).join(', ') || 'Sin asignar'}</p>
          )}
        </Panel>
      </aside>
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
        statusId={task.status?.id}
        statuses={statuses.data ?? []}
        disabled={updateStatus.isPending}
        onChange={(statusId) => updateStatus.mutate({ code: task.code, statusId })}
      />
      <FieldErrorsAlert error={updateStatus.error} />
    </div>
  )
}

function Fact({ term, children }: { term: string; children: ReactNode }) {
  return (
    <div className="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2 py-1.5">
      <dt className="text-muted">{term}</dt>
      <dd>{children}</dd>
    </div>
  )
}

function TaskFacts({ task }: { task: Task }) {
  const hasTime = useHasModule(MODULES.time)
  return (
    <dl className="divide-y divide-border text-sm">
      <Fact term="Prioridad">{taskPriorityLabels[task.priority]}</Fact>
      <Fact term="Categoría">{task.category?.name ?? <span className="text-muted">Sin categoría</span>}</Fact>
      <Fact term="Etiquetas">
        {task.labels && task.labels.length > 0 ? <LabelChips labels={task.labels} /> : <span className="text-muted">Ninguna</span>}
      </Fact>
      <Fact term="Inicio">{formatDate(task.starts_on)}</Fact>
      <Fact term="Vencimiento">{formatDate(task.due_on)}</Fact>
      <Fact term="Estimación">{task.estimate_seconds === null ? '—' : formatDuration(task.estimate_seconds)}</Fact>
      {hasTime && task.logged_seconds !== undefined && (
        <Fact term="Registradas">
          <span className="tabular font-medium">{formatDuration(task.logged_seconds)}</span>
        </Fact>
      )}
    </dl>
  )
}
