import { PageState } from '@/components/ui/PageState'
import { PersonCard } from '@/features/people/PersonCard'
import { useMyTeam } from './useMyProfile'

/** RF-01.07: the active people who report to me. */
export function MyTeamList() {
  const team = useMyTeam()

  if (!team.data) return <PageState isPending={team.isPending} error={team.error} shape="cards" onRetry={() => void team.refetch()} />
  if (team.data.length === 0) return <p className="text-sm text-muted">Nadie te reporta directamente.</p>

  return (
    <ul className="grid gap-3 sm:grid-cols-2" aria-label="Mi equipo">
      {team.data.map((person) => (
        <li key={person.id}>
          <PersonCard person={person} />
        </li>
      ))}
    </ul>
  )
}
