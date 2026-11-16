import { Link } from 'react-router'
import { Avatar } from '@/components/ui/Avatar'
import { AvatarStack } from '@/components/ui/AvatarStack'
import { HoursBar } from '@/features/hours/HoursBar'
import { ProjectStatusBadge } from './ProjectStatusBadge'
import type { ProjectSummary } from './types'

const memberNoun = ['miembro', 'miembros'] as const

/**
 * One project in the list: name (prefix after it, quiet), client underneath, the leader's face and the
 * members' faces, hours used as a number and a bar. The status only shows when it isn't the normal one (Activo).
 */
export function ProjectRow({ project }: { project: ProjectSummary }) {
  const others = (project.member_faces ?? []).filter((member) => member.id !== project.leader?.id)
  const othersTotal = Math.max((project.members_count ?? others.length) - (project.leader ? 1 : 0), others.length)

  return (
    <Link
      to={`/proyectos/${project.id}`}
      className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-4 py-3 transition-colors hover:bg-surface-2/60 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent sm:grid-cols-[minmax(0,1fr)_7.5rem_8rem_5.5rem]"
    >
      <span className="min-w-0">
        <span className="flex items-baseline gap-2">
          <span className="truncate font-medium">{project.name}</span>
          <span className="shrink-0 text-[13px] text-muted">{project.code_prefix}</span>
        </span>
        <span className="block truncate text-[13px] text-muted">{project.client?.name ?? 'Sin cliente'}</span>
      </span>
      <span className="flex items-center gap-2 justify-self-end sm:justify-self-start">
        {project.leader && (
          <span title={`Responsable: ${project.leader.full_name}`} className="inline-flex">
            <span className="sr-only">Responsable: {project.leader.full_name}</span>
            <Avatar name={project.leader.full_name} url={project.leader.avatar?.avatar} size="xs" />
          </span>
        )}
        <AvatarStack people={others} total={othersTotal} noun={memberNoun} />
      </span>
      <HoursBar bag={project.hours} compact />
      <span className="justify-self-end">{project.status !== 'active' && <ProjectStatusBadge status={project.status} />}</span>
    </Link>
  )
}
