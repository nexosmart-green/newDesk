import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { useProjectMembers } from '@/features/projects/useProjects'
import { AssigneeCheckboxes } from './AssigneeCheckboxes'
import type { Task } from './types'
import { useUpdateTaskAssignees } from './useTasks'

/**
 * Reassigning needs `tasks.assign` (the caller only renders this as a hint; the backend decides). The options
 * are the project's members; until they arrive, the current assignees are shown as text.
 */
export function TaskAssigneesEditor({ task }: { task: Task }) {
  const members = useProjectMembers(task.project?.id)
  const update = useUpdateTaskAssignees(task.code)
  const [selected, setSelected] = useState(() => (task.assignees ?? []).map((assignee) => assignee.id))

  if (!members.data) {
    return <p className="text-sm">{(task.assignees ?? []).map((assignee) => assignee.full_name).join(', ') || 'Sin asignar'}</p>
  }

  return (
    <div className="space-y-2">
      <AssigneeCheckboxes
        members={members.data}
        selected={selected}
        onChange={(ids) => {
          update.reset()
          setSelected(ids)
        }}
      />
      <FieldErrorsAlert error={update.error} />
      <Button variant="secondary" loading={update.isPending} onClick={() => update.mutate(selected)}>
        Guardar asignados
      </Button>
    </div>
  )
}
