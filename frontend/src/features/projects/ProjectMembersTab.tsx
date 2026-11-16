import { useState } from 'react'
import { Link } from 'react-router'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { PersonSelect } from '@/features/people/PersonSelect'
import type { PersonSummary } from '@/features/people/types'
import { useProjectFromOutlet } from './projectContext'
import { useAddProjectMember, useRemoveProjectMember } from './useProjects'

/** RF-03.03: only members see the project and can receive its tasks. */
export function ProjectMembersTab() {
  const project = useProjectFromOutlet()
  const [toAdd, setToAdd] = useState<number | null>(null)
  const add = useAddProjectMember(project.id)
  const memberIds = project.members.map((member) => member.id)

  function handleAdd() {
    if (toAdd === null) return
    add.mutate(toAdd, { onSuccess: () => setToAdd(null) })
  }

  return (
    <div className="space-y-4">
      <FieldErrorsAlert error={add.error} />
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
            <MemberRow
              key={member.id}
              projectId={project.id}
              member={member}
              isLeader={member.id === project.leader?.id}
              canRemove={project.can.manage_members}
            />
          ))}
        </ul>
      )}
    </div>
  )
}

interface MemberRowProps {
  projectId: number
  member: PersonSummary
  isLeader: boolean
  canRemove: boolean
}

/** Its own "Quitar": removing one member only makes that row wait. */
function MemberRow({ projectId, member, isLeader, canRemove }: MemberRowProps) {
  const remove = useRemoveProjectMember(projectId)
  return (
    <li className="space-y-2 p-3">
      <div className="flex items-center gap-3">
        <Avatar name={member.full_name} url={member.avatar?.avatar} size="sm" />
        <Link to={`/personas/${member.id}`} className="min-w-0 flex-1">
          <span className="block font-medium">{member.full_name}</span>
          <span className="block truncate text-sm text-muted">{member.job_title?.name ?? member.email}</span>
        </Link>
        {isLeader && <Badge tone="accent">Responsable</Badge>}
        {member.status === 'inactive' && <Badge>De baja</Badge>}
        {canRemove && (
          <Button
            variant="link"
            className="text-danger"
            aria-label={`Quitar a ${member.full_name}`}
            loading={remove.isPending}
            onClick={() => remove.mutate(member.id)}
          >
            Quitar
          </Button>
        )}
      </div>
      <FieldErrorsAlert error={remove.error} />
    </li>
  )
}
