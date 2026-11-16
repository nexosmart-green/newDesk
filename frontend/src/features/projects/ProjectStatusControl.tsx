import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { badgeClassName } from '@/components/ui/badgeStyles'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Menu } from '@/components/ui/Menu'
import { ProjectStatusBadge } from './ProjectStatusBadge'
import {
  isClosedStatus,
  projectStatuses,
  projectStatusLabels,
  projectStatusTones,
  projectStatusVerbs,
  type ProjectDetail,
  type ProjectStatus,
} from './types'
import { useChangeProjectStatus } from './useProjects'

/**
 * RF-03.08: the status in the project's header, as a badge that opens a menu for whoever may change it.
 * Closing (Finalizado, Cancelado) stops running timers, so it asks first in danger style; reopening a closed
 * project is an admin call (the backend answers `reopen_forbidden`).
 */
export function ProjectStatusControl({ project }: { project: ProjectDetail }) {
  const [closingTo, setClosingTo] = useState<ProjectStatus | null>(null)
  const mutation = useChangeProjectStatus(project.id)

  if (!project.can.update) return <ProjectStatusBadge status={project.status} />

  function choose(status: ProjectStatus) {
    if (status === project.status) return
    mutation.reset()
    if (isClosedStatus(status) && !isClosedStatus(project.status)) setClosingTo(status)
    else mutation.mutate(status)
  }

  const verb = closingTo ? projectStatusVerbs[closingTo].action : ''
  return (
    <>
      <Menu
        label={`Estado: ${projectStatusLabels[project.status]}. Cambiar estado`}
        align="start"
        trigger={
          <>
            {projectStatusLabels[project.status]}
            <ChevronDown aria-hidden="true" className="size-3.5" />
          </>
        }
        triggerClassName={`${badgeClassName(projectStatusTones[project.status])} min-h-6 transition-colors hover:bg-surface-2 coarse:min-h-11`}
        items={projectStatuses.map((status) => ({
          label: projectStatusLabels[status],
          checked: status === project.status,
          disabled: mutation.isPending,
          onSelect: () => choose(status),
        }))}
      />
      {closingTo && (
        <ConfirmDialog
          title={`¿${verb} «${project.name}»?`}
          confirmLabel={`${verb} proyecto`}
          // "Cancelar" next to "Cancelar proyecto" would read as the same thing.
          cancelLabel="Volver"
          danger
          pending={mutation.isPending}
          error={mutation.error}
          onClose={() => setClosingTo(null)}
          onConfirm={() => mutation.mutate(closingTo, { onSuccess: () => setClosingTo(null) })}
        >
          <p>
            Los cronómetros que estén corriendo en este proyecto se detienen ahora, y no se van a poder crear tareas ni cargar horas
            nuevas.
          </p>
          <p>Reabrirlo después lo puede hacer solo un administrador.</p>
        </ConfirmDialog>
      )}
    </>
  )
}
