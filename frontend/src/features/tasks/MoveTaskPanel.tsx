import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import { toast } from '@/components/ui/toast/toastStore'
import { useProjects } from '@/features/projects/useProjects'
import type { Task } from './types'
import { useMoveTask } from './useTasks'

const ACTIVE_PROJECTS = { client_id: null, status: 'active', search: '', running_out: false, page: 1 } as const

/**
 * RF-04.12: "me equivoqué de proyecto". The task, its hours, comments and the assignees who belong to the
 * destination move together; the backend decides who may do it and whether the destination has room.
 */
export function MoveTaskPanel({ task }: { task: Task }) {
  const navigate = useNavigate()
  const projects = useProjects(ACTIVE_PROJECTS)
  const move = useMoveTask(task.code)
  const [destinationId, setDestinationId] = useState<number | null>(null)
  const [confirming, setConfirming] = useState(false)

  const options = (projects.data?.data ?? []).filter((project) => project.id !== task.project?.id)
  const destination = options.find((project) => project.id === destinationId)

  function confirmMove() {
    if (destinationId === null) return
    move.mutate(destinationId, {
      onSuccess: (result) => {
        setConfirming(false)
        const removed = result.unassigned.length > 0 ? ` Se quitaron de la tarea: ${result.unassigned.join(', ')}.` : ''
        toast.success(`Ahora es ${result.task.code}. El código ${task.code} sigue funcionando.${removed}`, { title: 'Tarea movida' })
        navigate(`/tareas/${result.task.code}`, { replace: true })
      },
    })
  }

  return (
    <div className="space-y-3">
      <p className="text-sm text-muted">
        Si la tarea se cargó en el proyecto equivocado, movela: sus horas pasan a la bolsa del proyecto destino.
      </p>
      <div className="flex flex-wrap items-end gap-2">
        <SelectField
          label="Proyecto destino"
          value={destinationId ?? ''}
          onChange={(event) => setDestinationId(event.target.value ? Number(event.target.value) : null)}
          className="min-w-56 flex-1"
        >
          <option value="">Elegí un proyecto…</option>
          {options.map((project) => (
            <option key={project.id} value={project.id}>
              {project.name} ({project.code_prefix})
            </option>
          ))}
        </SelectField>
        <Button variant="secondary" disabled={destinationId === null || move.isPending} onClick={() => setConfirming(true)}>
          Mover tarea
        </Button>
      </div>
      {!confirming && <FieldErrorsAlert error={move.error} />}
      {confirming && destination && (
        <ConfirmDialog
          title="Mover tarea"
          confirmLabel="Mover"
          pending={move.isPending}
          error={move.error}
          onConfirm={confirmMove}
          onClose={() => setConfirming(false)}
        >
          <p className="text-sm">
            {task.code} pasa a {destination.name} con un código nuevo. Sus horas dejan de contar en{' '}
            {task.project?.name ?? 'el proyecto actual'} y pasan a {destination.name}.
          </p>
        </ConfirmDialog>
      )}
    </div>
  )
}
