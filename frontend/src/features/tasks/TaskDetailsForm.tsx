import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { hasOptionalValues, taskFormValuesOf, toSaveTaskInput, type TaskFormValues } from './taskForm'
import { TaskFormFields } from './TaskFormFields'
import type { Task } from './types'
import { useUpdateTask } from './useTasks'

interface TaskDetailsFormProps {
  task: Task
  /** After saving (and on "Cancelar", when given): back to the read view. */
  onDone?: () => void
}

/** RF-04.01: any project member edits the task (P-04). */
export function TaskDetailsForm({ task, onDone }: TaskDetailsFormProps) {
  const update = useUpdateTask(task.code)
  const [values, setValues] = useState<TaskFormValues>(() => taskFormValuesOf(task))

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    update.mutate(toSaveTaskInput(values), { onSuccess: () => onDone?.() })
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4" aria-label="Detalles de la tarea">
      <TaskFormFields
        values={values}
        error={update.error}
        optionsOpen={hasOptionalValues(values)}
        projectId={task.project?.id}
        currentCategory={task.category}
        onChange={(changes) => {
          update.reset()
          setValues((current) => ({ ...current, ...changes }))
        }}
      />
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        {onDone && (
          <Button variant="ghost" onClick={onDone}>
            Cancelar
          </Button>
        )}
        <Button type="submit" loading={update.isPending}>
          Guardar cambios
        </Button>
      </div>
    </form>
  )
}
