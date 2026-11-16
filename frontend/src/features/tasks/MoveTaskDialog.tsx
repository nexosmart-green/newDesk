import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
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
 * Once a destination is picked the dialog says what happens, so the button itself is the confirmation.
 */
export function MoveTaskDialog({ task, onClose }: { task: Task; onClose: () => void }) {
  const navigate = useNavigate()
  const projects = useProjects(ACTIVE_PROJECTS)
  const move = useMoveTask(task.code)
  const [destinationId, setDestinationId] = useState<number | null>(null)

  const options = (projects.data?.data ?? []).filter((project) => project.id !== task.project?.id)
  const destination = options.find((project) => project.id === destinationId)

  function confirmMove() {
    if (destinationId === null) return
    move.mutate(destinationId, {
      onSuccess: (result) => {
        onClose()
        const removed = result.unassigned.length > 0 ? ` Se quitaron de la tarea: ${result.unassigned.join(', ')}.` : ''
        toast.success(`Ahora es ${result.task.code}. El código ${task.code} sigue funcionando.${removed}`, { title: 'Tarea movida' })
        navigate(`/tareas/${result.task.code}`, { replace: true })
      },
    })
  }

  return (
    <Dialog
      title="Mover a otro proyecto"
      description="Para una tarea que se cargó en el proyecto equivocado."
      dirty={destinationId !== null}
      onClose={onClose}
    >
      <SelectField
        label="Proyecto destino"
        value={destinationId ?? ''}
        data-autofocus
        onChange={(event) => {
          move.reset()
          setDestinationId(event.target.value ? Number(event.target.value) : null)
        }}
      >
        <option value="">Elegí un proyecto…</option>
        {options.map((project) => (
          <option key={project.id} value={project.id}>
            {project.name} ({project.code_prefix})
          </option>
        ))}
      </SelectField>
      <p className="text-sm leading-relaxed text-muted" aria-live="polite">
        {destination
          ? `${task.code} pasa a ${destination.name} con un código nuevo; el actual sigue funcionando. Sus horas dejan de contar en ${task.project?.name ?? 'el proyecto actual'} y pasan a la bolsa de ${destination.name}.`
          : 'Sus horas, comentarios y archivos van con ella; las horas pasan a la bolsa del proyecto destino.'}
      </p>
      <FieldErrorsAlert error={move.error} />
      <DialogFooter>
        <Button variant="secondary" onClick={onClose}>
          Cancelar
        </Button>
        <Button disabled={destinationId === null} loading={move.isPending} onClick={confirmMove}>
          Mover tarea
        </Button>
      </DialogFooter>
    </Dialog>
  )
}
