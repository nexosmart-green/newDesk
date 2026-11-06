import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { AssigneeCheckboxes } from './AssigneeCheckboxes'
import type { Task } from './types'
import { useUpdateTaskAssignees } from './useTasks'

interface TaskAssigneesEditorProps {
  task: Task
  members: { id: number; full_name: string }[]
}

/** Reassigning needs `tasks.assign` (the caller only renders this as a hint; the backend decides). */
export function TaskAssigneesEditor({ task, members }: TaskAssigneesEditorProps) {
  const update = useUpdateTaskAssignees(task.code)
  const [selected, setSelected] = useState(() => (task.assignees ?? []).map((assignee) => assignee.id))

  return (
    <div className="space-y-2">
      <AssigneeCheckboxes
        members={members}
        selected={selected}
        onChange={(ids) => {
          update.reset()
          setSelected(ids)
        }}
      />
      <ErrorAlert error={update.error} showFieldErrors />
      {update.isSuccess && <SuccessNotice message="Asignación actualizada." />}
      <Button variant="secondary" disabled={update.isPending} onClick={() => update.mutate(selected)}>
        Guardar asignados
      </Button>
    </div>
  )
}
