import { Link } from 'react-router'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { PersonRole } from './PersonRole'
import type { PersonSummary } from './types'

export function PersonCard({ person }: { person: PersonSummary }) {
  return (
    <Link
      to={`/personas/${person.id}`}
      className="flex items-center gap-3 rounded-lg border border-border bg-surface p-3 hover:border-accent focus-visible:outline-2 focus-visible:outline-accent"
    >
      <Avatar name={person.full_name} url={person.avatar?.avatar} />
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-medium">{person.full_name}</span>
          {person.status === 'inactive' && <Badge>De baja</Badge>}
        </div>
        <PersonRole person={person} fallback={person.email} />
        {person.skills?.map((skill) => (
          <p key={skill.id} className="text-xs text-accent">
            {skill.name}: {skill.level}/100
          </p>
        ))}
      </div>
    </Link>
  )
}
