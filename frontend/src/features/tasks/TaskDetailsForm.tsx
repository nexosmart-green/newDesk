import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { hasOptionalValues, taskFormValuesOf, toSaveTaskInput, type TaskFormValues } from './taskForm'
import { TaskFormFields } from './TaskFormFields'
import type { Task } from './types'
import { useUpdateTask } from './useTasks'

/** RF-04.01: any project member edits the task (P-04). */
export function TaskDetailsForm({ task }: { task: Task }) {
  const update = useUpdateTask(task.code)
  const [values, setValues] = useState<TaskFormValues>(() => taskFormValuesOf(task))

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    update.mutate(toSaveTaskInput(values))
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3" aria-label="Detalles de la tarea">
      <TaskFormFields
        values={values}
        error={update.error}
        optionsOpen={hasOptionalValues(values)}
        onChange={(changes) => {
          update.reset()
          setValues((current) => ({ ...current, ...changes }))
        }}
      />
      <div className="flex justify-end">
        <Button type="submit" loading={update.isPending}>
          Guardar cambios
        </Button>
      </div>
    </form>
  )
}
