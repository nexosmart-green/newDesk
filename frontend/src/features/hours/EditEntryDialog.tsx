import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { ACTIVE_PROJECTS, useProjects } from '@/features/projects/useProjects'
import { defaultTaskFilters } from '@/features/tasks/types'
import { useProjectTasks } from '@/features/tasks/useTasks'
import { CommentField } from '@/features/timer/CommentField'
import { isValidComment, type CorrectEntryInput, type TimeEntry } from '@/features/timer/types'
import { useCorrectTimeEntry } from '@/features/timer/useTimer'
import { fieldErrorOf } from '@/lib/api'
import { useProfileTimeZone } from '@/features/auth/useAuth'
import { entryFormTimes } from './entryTimes'

interface EditEntryDialogProps {
  entry: TimeEntry & { ended_at: string }
  onClose: () => void
}

/**
 * RF-05.15: the window and who may are backend rules; its message is shown as is. The entry can move to another
 * open task of its owner, even in another project (its hours then count in that project's bag).
 */
export function EditEntryDialog({ entry, onClose }: EditEntryDialogProps) {
  const correct = useCorrectTimeEntry()
  const timeZone = useProfileTimeZone()
  // Prefill in the profile timezone: the backend reads the submitted date/from/to in that same zone.
  const [initial] = useState(() => entryFormTimes(entry.started_at, entry.ended_at, timeZone))
  const [range, setRange] = useState(initial)
  const [comment, setComment] = useState(entry.comment ?? '')
  const [projectId, setProjectId] = useState(entry.project.id)
  const [taskId, setTaskId] = useState<number | null>(entry.task.id)
  const projects = useProjects(ACTIVE_PROJECTS)
  const tasks = useProjectTasks(projectId, { ...defaultTaskFilters, assignee_id: entry.user.id })
  const timesChanged = range.date !== initial.date || range.from !== initial.from || range.to !== initial.to

  const projectOptions = projects.data?.data ?? []
  // Right after a project change the previous project's tasks are placeholders: never offer them.
  const loadingTasks = tasks.isPending || tasks.isPlaceholderData
  const taskOptions = loadingTasks ? [] : (tasks.data?.data ?? [])
  // The current project and task stay pickable even if closed or no longer listed.
  const keepProject = !projectOptions.some((project) => project.id === entry.project.id)
  const keepTask = projectId === entry.project.id && !taskOptions.some((task) => task.id === entry.task.id)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (!isValidComment(comment) || taskId === null) return
    // Only send what changed: a comment-only fix keeps the stored instants and task untouched.
    const input: CorrectEntryInput = {
      ...(timesChanged ? range : {}),
      ...(taskId !== entry.task.id ? { task_id: taskId } : {}),
      comment: comment.trim(),
    }
    correct.mutate({ entryId: entry.id, input }, { onSuccess: onClose })
  }

  return (
    <Dialog title={`Corregir registro de ${entry.task.code}`} description={entry.task.title} size="lg" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <SelectField
            label="Proyecto"
            value={projectId}
            onChange={(event) => {
              setProjectId(Number(event.target.value))
              setTaskId(null)
            }}
          >
            {keepProject && <option value={entry.project.id}>{entry.project.name}</option>}
            {projectOptions.map((project) => (
              <option key={project.id} value={project.id}>
                {project.name}
              </option>
            ))}
          </SelectField>
          <SelectField
            label="Tarea"
            required
            value={taskId ?? ''}
            onChange={(event) => setTaskId(event.target.value ? Number(event.target.value) : null)}
            error={fieldErrorOf(correct.error, 'task_id')}
          >
            <option value="">{loadingTasks ? 'Cargando…' : 'Elegí una tarea…'}</option>
            {keepTask && (
              <option value={entry.task.id}>
                {entry.task.code.toUpperCase()} · {entry.task.title}
              </option>
            )}
            {taskOptions.map((task) => (
              <option key={task.id} value={task.id}>
                {task.code.toUpperCase()} · {task.title}
              </option>
            ))}
          </SelectField>
        </div>
        <TextField
          label="Fecha"
          type="date"
          required
          value={range.date}
          hint={`Fecha y horas en tu zona horaria (${timeZone}).`}
          onChange={(event) => setRange({ ...range, date: event.target.value })}
          error={fieldErrorOf(correct.error, 'date')}
        />
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-[1fr_1fr]">
          <TextField
            label="Desde"
            type="time"
            required
            value={range.from}
            onChange={(event) => setRange({ ...range, from: event.target.value })}
            error={fieldErrorOf(correct.error, 'from')}
          />
          <TextField
            label="Hasta"
            type="time"
            required
            value={range.to}
            onChange={(event) => setRange({ ...range, to: event.target.value })}
            error={fieldErrorOf(correct.error, 'to')}
          />
        </div>
        <CommentField label="Comentario" value={comment} onChange={setComment} error={fieldErrorOf(correct.error, 'comment')} />
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={correct.isPending} disabled={!isValidComment(comment) || taskId === null}>
            Guardar
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
