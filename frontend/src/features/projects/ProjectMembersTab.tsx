import { useState } from 'react'
import { Link } from 'react-router'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { PersonSelect } from '@/features/people/PersonSelect'
import { useProjectFromOutlet } from './projectContext'
import { useAddProjectMember, useRemoveProjectMember } from './useProjects'

/** RF-03.03: only members see the project and can receive its tasks. */
export function ProjectMembersTab() {
  const project = useProjectFromOutlet()
  const [toAdd, setToAdd] = useState<number | null>(null)
  const add = useAddProjectMember(project.id)
  const remove = useRemoveProjectMember(project.id)
  const memberIds = project.members.map((member) => member.id)

  function handleAdd() {
    if (toAdd === null) return
    remove.reset()
    add.mutate(toAdd, { onSuccess: () => setToAdd(null) })
  }

  function handleRemove(userId: number) {
    add.reset()
    remove.mutate(userId)
  }

  return (
    <div className="space-y-4">
      <FieldErrorsAlert error={add.error ?? remove.error} />
      {project.can.manage_members && (
        <div className="flex items-end gap-2">
          <PersonSelect
            label="Agregar miembro"
            emptyLabel="Elegí una persona"
            value={toAdd}
            excludeIds={memberIds}
            onChange={setToAdd}
          />
          <Button disabled={toAdd === null} loading={add.isPending} onClick={handleAdd}>
            Agregar
          </Button>
        </div>
      )}
      {project.members.length === 0 ? (
        <p className="text-sm text-muted">Este proyecto todavía no tiene miembros.</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Miembros">
          {project.members.map((member) => (
            <li key={member.id} className="flex items-center gap-3 p-3">
              <Avatar name={member.full_name} url={member.avatar?.avatar} size="sm" />
              <Link to={`/personas/${member.id}`} className="min-w-0 flex-1">
                <span className="block font-medium">{member.full_name}</span>
                <span className="block truncate text-sm text-muted">{member.job_title?.name ?? member.email}</span>
              </Link>
              {member.id === project.leader?.id && <Badge tone="accent">Responsable</Badge>}
              {member.status === 'inactive' && <Badge>De baja</Badge>}
              {project.can.manage_members && (
                <Button
                  variant="link"
                  className="text-danger"
                  aria-label={`Quitar a ${member.full_name}`}
                  loading={remove.isPending && remove.variables === member.id}
                  disabled={remove.isPending}
                  onClick={() => handleRemove(member.id)}
                >
                  Quitar
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
