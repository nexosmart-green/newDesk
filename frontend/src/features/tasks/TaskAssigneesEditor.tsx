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
export function TaskAssigneesEditor({ task, onDone }: { task: Task; onDone: () => void }) {
  const members = useProjectMembers(task.project?.id)
  const update = useUpdateTaskAssignees(task.code)
  const [selected, setSelected] = useState(() => (task.assignees ?? []).map((assignee) => assignee.id))

  if (!members.data) {
    return <p className="text-sm">{(task.assignees ?? []).map((assignee) => assignee.full_name).join(', ') || 'Sin asignar'}</p>
  }

  return (
    <div className="space-y-2">
      <AssigneeCheckboxes
        hideLegend
        members={members.data}
        selected={selected}
        onChange={(ids) => {
          update.reset()
          setSelected(ids)
        }}
      />
      <FieldErrorsAlert error={update.error} />
      <div className="flex flex-wrap gap-2">
        <Button loading={update.isPending} onClick={() => update.mutate(selected, { onSuccess: onDone })}>
          Guardar asignados
        </Button>
        <Button variant="ghost" onClick={onDone}>
          Cancelar
        </Button>
      </div>
    </div>
  )
}
