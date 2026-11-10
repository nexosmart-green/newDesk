import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
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
  projectId: number
  members: { id: number; full_name: string }[]
  onClose: () => void
}

const isCommentError = (error: unknown) =>
  error instanceof ApiError &&
  (error.code === timerRule.previousCommentRequired || error.code === timerRule.commentRequired || error.hasFieldErrors())

/**
 * RF-04.01/02. "Crear e iniciar" with another timer running asks for that timer's comment first
 * (CA-04.02); if the timer can't start the task stays created and the reason is shown (CA-04.02b).
 */
export function CreateTaskDialog({ projectId, members, onClose }: CreateTaskDialogProps) {
  const create = useCreateTask(projectId)
  const createAndStart = useCreateAndStartTask(projectId)
  const [values, setValues] = useState<TaskFormValues>(emptyTaskForm)
  const [assigneeIds, setAssigneeIds] = useState<number[]>([])
  const [askingPrevious, setAskingPrevious] = useState(false)
  const [partial, setPartial] = useState<CreateAndStartResult | null>(null)
  const [titleMissing, setTitleMissing] = useState(false)

  const input = (): SaveTaskInput => ({ ...toSaveTaskInput(values), assignee_ids: assigneeIds })

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
      <Dialog title="Tarea creada" onClose={onClose}>
        <p className="text-sm">
          Tarea <span className="font-mono uppercase">{task.code}</span> creada, pero el cronómetro no arrancó.
        </p>
        <TimerBlockedNotice code={timerError.code} message={timerError.message} projectId={projectId} />
        <div className="flex justify-end gap-2">
          <Link to={`/tareas/${task.code}`} className="px-3 py-2 text-sm text-accent">
            Abrir {task.code}
          </Link>
          <Button variant="secondary" onClick={onClose}>
            Cerrar
          </Button>
        </div>
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
  const pending = create.isPending || createAndStart.isPending

  return (
    <Dialog title="Nueva tarea" onClose={onClose}>
      <form onSubmit={handleCreate} className="space-y-4">
        <TaskFormFields
          values={values}
          error={error}
          onChange={(changes) => {
            setTitleMissing(false)
            setValues((current) => ({ ...current, ...changes }))
          }}
        />
        {titleMissing && <p className="text-sm text-danger">El título es obligatorio.</p>}
        <AssigneeCheckboxes members={members} selected={assigneeIds} onChange={setAssigneeIds} />
        <ErrorAlert error={error} />
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" variant="secondary" disabled={pending}>
            Crear
          </Button>
          <Button disabled={pending} onClick={() => startWith()}>
            <span aria-hidden="true">▶ </span>Crear e iniciar
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
