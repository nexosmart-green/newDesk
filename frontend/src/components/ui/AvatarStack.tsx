import { plural } from '@/lib/plural'
import { Avatar } from './Avatar'

interface AvatarStackProps {
  /** The faces to show (the first few). */
  people: { id: number; full_name: string; avatar?: { avatar: string } | null }[]
  /** How many there are in all, when `people` is only the first few. */
  total?: number
  /** Singular and plural of what they are: ["miembro", "miembros"]. */
  noun: readonly [string, string]
}

/** Overlapping faces for "who's in": the names in the tooltip and for screen readers, "+n" for the rest. */
export function AvatarStack({ people, total = people.length, noun }: AvatarStackProps) {
  if (total === 0) return null
  const rest = Math.max(total - people.length, 0)
  const names = people.map((person) => person.full_name).join(', ')
  const description = `${plural(total, ...noun)}: ${names}${rest > 0 ? ` y ${rest} más` : ''}`
  return (
    <span className="inline-flex items-center" title={description}>
      <span className="sr-only">{description}</span>
      <span aria-hidden="true" className="flex -space-x-1.5">
        {people.map((person) => (
          <span key={person.id} className="rounded-full ring-2 ring-surface">
            <Avatar name={person.full_name} url={person.avatar?.avatar} size="xs" />
          </span>
        ))}
        {rest > 0 && (
          <span className="tabular grid size-6 place-items-center rounded-full bg-surface-2 text-[11px] font-medium text-muted ring-2 ring-surface">
            +{rest}
          </span>
        )}
      </span>
    </span>
  )
}
