import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import {
  isClosedStatus,
  isProjectStatus,
  projectStatuses,
  projectStatusLabels,
  type ProjectDetail,
  type ProjectStatus,
} from './types'
import { useChangeProjectStatus } from './useProjects'

/** RF-03.08: closing stops running timers; reopening is an admin call (`reopen_forbidden`). */
export function ProjectStatusControl({ project }: { project: ProjectDetail }) {
  const [status, setStatus] = useState<ProjectStatus>(project.status)
  const [confirming, setConfirming] = useState(false)
  const mutation = useChangeProjectStatus(project.id)

  function apply() {
    mutation.mutate(status, { onSettled: () => setConfirming(false) })
  }

  function handleApply() {
    mutation.reset()
    if (isClosedStatus(status) && !isClosedStatus(project.status)) setConfirming(true)
    else apply()
  }

  return (
    <div className="space-y-2">
      <div className="flex items-end gap-2">
        <SelectField
          label="Estado"
          value={status}
          onChange={(event) => isProjectStatus(event.target.value) && setStatus(event.target.value)}
        >
          {projectStatuses.map((value) => (
            <option key={value} value={value}>
              {projectStatusLabels[value]}
            </option>
          ))}
        </SelectField>
        <Button disabled={status === project.status} loading={mutation.isPending && !confirming} onClick={handleApply}>
          Cambiar estado
        </Button>
      </div>
      {!confirming && <FieldErrorsAlert error={mutation.error} />}
      {confirming && (
        <ConfirmDialog
          title={`Marcar como ${projectStatusLabels[status].toLowerCase()}`}
          confirmLabel="Confirmar"
          pending={mutation.isPending}
          error={mutation.error}
          onClose={() => setConfirming(false)}
          onConfirm={apply}
        >
          <p>El proyecto no va a admitir tareas nuevas ni cronómetros, y los que estén corriendo se detienen ahora.</p>
          <p>Reabrirlo después lo puede hacer solo un administrador.</p>
        </ConfirmDialog>
      )}
    </div>
  )
}
