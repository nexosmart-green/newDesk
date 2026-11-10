import { Play } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import { useCurrentUser } from '@/features/auth/useAuth'
import { ACTIVE_PROJECTS, useProject, useProjects } from '@/features/projects/useProjects'
import { CommentDialog } from '@/features/timer/CommentDialog'
import { TimerBlockedNotice } from '@/features/timer/TimerBlockedNotice'
import { timerRule } from '@/features/timer/types'
import { ApiError } from '@/lib/api'
import { AssigneeCheckboxes } from './AssigneeCheckboxes'
import { emptyTaskForm, toSaveTaskInput, type TaskFormValues } from './taskForm'
import { TaskFormFields } from './TaskFormFields'
import type { CreateAndStartResult, SaveTaskInput } from './types'
import { useCreateAndStartTask, useCreateTask } from './useTasks'

interface CreateTaskDialogProps {
  /** Fixed from a project's Tareas tab; without it (Mis tareas) the person picks the project (CA-04.07b). */
  project?: { id: number; members: { id: number; full_name: string }[] }
  onClose: () => void
}

const isCommentError = (error: unknown) =>
  error instanceof ApiError &&
  (error.code === timerRule.previousCommentRequired || error.code === timerRule.commentRequired || error.hasFieldErrors())

/**
 * RF-04.01/02. "Crear e iniciar" with another timer running asks for that timer's comment first
 * (CA-04.02); if the timer can't start the task stays created and the reason is shown (CA-04.02b).
 */
export function CreateTaskDialog({ project, onClose }: CreateTaskDialogProps) {
  const { data: me } = useCurrentUser()
  const projects = useProjects(ACTIVE_PROJECTS, { enabled: !project })
  const options = project ? [] : (projects.data?.data ?? [])
  const [pickedId, setPickedId] = useState(0)
  // A single project needs no choosing.
  const projectId = project?.id ?? (pickedId || (options.length === 1 ? (options[0]?.id ?? 0) : 0))
  const picked = useProject(project ? 0 : projectId)
  const members = project?.members ?? picked.data?.members ?? []
  const create = useCreateTask(projectId)
  const createAndStart = useCreateAndStartTask(projectId)
  const [values, setValues] = useState<TaskFormValues>(emptyTaskForm)
  // null = untouched: from Mis tareas the person is ticked when they are a member, so the task lands in their list.
  const [chosenAssignees, setChosenAssignees] = useState<number[] | null>(null)
  const [askingPrevious, setAskingPrevious] = useState(false)
  const [partial, setPartial] = useState<CreateAndStartResult | null>(null)
  const [titleMissing, setTitleMissing] = useState(false)

  const assigneeIds = chosenAssignees ?? (!project && me && members.some((member) => member.id === me.id) ? [me.id] : [])
  const input = (): SaveTaskInput => ({ ...toSaveTaskInput(values), assignee_ids: assigneeIds })

  function pickProject(id: number) {
    setPickedId(id)
    setChosenAssignees(null)
  }

  function handleCreate(event: FormEvent) {
    event.preventDefault()
    createAndStart.reset()
    create.mutate(input(), { onSuccess: onClose })
  }

  function startWith(previousComment?: string) {
    if (!values.title.trim()) return setTitleMissing(true)
    create.reset()
    createAndStart.mutate(
      { input: input(), previousComment },
      {
        onSuccess: (result) => {
          setAskingPrevious(false)
          if (result.timer_error) setPartial(result)
          else onClose()
        },
        onError: (error) => {
          if (error instanceof ApiError && error.code === timerRule.previousCommentRequired && !previousComment) {
            setAskingPrevious(true)
          } else if (!(previousComment && isCommentError(error))) {
            setAskingPrevious(false)
          }
        },
      },
    )
  }

  if (partial?.timer_error) {
    const { task, timer_error: timerError } = partial
    return (
      <Dialog title="Tarea creada" size="sm" onClose={onClose}>
        <p className="text-sm">
          Tarea <span className="font-mono uppercase">{task.code}</span> creada, pero el cronómetro no arrancó.
        </p>
        <TimerBlockedNotice code={timerError.code} message={timerError.message} projectId={projectId} />
        <DialogFooter>
          <Link
            to={`/tareas/${task.code}`}
            className="inline-flex min-h-11 items-center justify-center rounded-lg px-3 text-sm font-medium text-accent hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent"
          >
            Abrir {task.code}
          </Link>
          <Button variant="secondary" onClick={onClose}>
            Cerrar
          </Button>
        </DialogFooter>
      </Dialog>
    )
  }

  if (askingPrevious) {
    return (
      <CommentDialog
        title="Antes de cambiar de tarea"
        submitLabel="Detener y crear"
        pending={createAndStart.isPending}
        error={createAndStart.error}
        onSubmit={(comment) => startWith(comment)}
        onClose={() => setAskingPrevious(false)}
      >
        <p>Tenés un cronómetro corriendo. Contá qué hiciste para detenerlo y arrancar la tarea nueva.</p>
      </CommentDialog>
    )
  }

  const error = create.error ?? createAndStart.error
  const pending = create.isPending || createAndStart.isPending || projectId === 0
  // Create errors toast (global); the create-and-start flow explains its own here.
  const inlineError = createAndStart.error

  return (
    <Dialog title="Nueva tarea" description="Solo el título es obligatorio." size="lg" onClose={onClose}>
      <form onSubmit={handleCreate} className="space-y-4">
        {!project && (
          <SelectField label="Proyecto" value={projectId || ''} onChange={(event) => pickProject(Number(event.target.value))} required>
            <option value="">{projects.isPending ? 'Cargando proyectos…' : 'Elegí un proyecto…'}</option>
            {options.map((option) => (
              <option key={option.id} value={option.id}>
                {option.name} ({option.code_prefix})
              </option>
            ))}
          </SelectField>
        )}
        {!project && projects.data && options.length === 0 && (
          <p className="text-sm text-muted">No estás en ningún proyecto activo: pedile a quien lo gestiona que te sume.</p>
        )}
        <TaskFormFields
          values={values}
          error={error}
          onChange={(changes) => {
            setTitleMissing(false)
            setValues((current) => ({ ...current, ...changes }))
          }}
        />
        {titleMissing && <p className="text-sm text-danger">El título es obligatorio.</p>}
        {projectId > 0 && <AssigneeCheckboxes members={members} selected={assigneeIds} onChange={setChosenAssignees} />}
        <ErrorAlert error={inlineError} />
        <DialogFooter>
          <Button variant="ghost" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" variant="secondary" loading={create.isPending} disabled={pending}>
            Crear
          </Button>
          <Button loading={createAndStart.isPending} disabled={pending} onClick={() => startWith()}>
            <Play aria-hidden="true" className="size-4 fill-current" />
            Crear e iniciar
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
