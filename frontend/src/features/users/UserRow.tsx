import { Button } from '@/components/ui/Button'
import { userStatusLabels, type UserSummary } from './types'

interface UserRowProps {
  user: UserSummary
  roles: Pick<UserSummary['role'], 'id' | 'name'>[]
  onChangeRole: (user: UserSummary, roleId: number) => void
  onResendInvitation: (user: UserSummary) => void
  busy: boolean
}

const statusClassNames: Record<UserSummary['status'], string> = {
  invited: 'text-warning',
  active: 'text-success',
  inactive: 'text-muted',
}

function formatLastLogin(value: string | null): string {
  if (!value) return 'Nunca'
  return new Date(value).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' })
}

export function UserRow({ user, roles, onChangeRole, onResendInvitation, busy }: UserRowProps) {
  return (
    <tr className="border-t border-border align-top">
      <td className="py-2 pr-3">
        <div className="font-medium">{user.full_name}</div>
        <div className="break-all text-xs text-muted">{user.email}</div>
      </td>
      <td className="py-2 pr-3">
        <select
          aria-label={`Rol de ${user.full_name}`}
          value={user.role.id}
          disabled={busy}
          onChange={(event) => onChangeRole(user, Number(event.target.value))}
          className="w-full min-w-28 rounded border border-border bg-bg px-2 py-1 text-sm disabled:opacity-60"
        >
          {roles.map((role) => (
            <option key={role.id} value={role.id}>
              {role.name}
            </option>
          ))}
        </select>
      </td>
      <td className={`hidden py-2 pr-3 text-sm sm:table-cell ${statusClassNames[user.status]}`}>
        {userStatusLabels[user.status]}
      </td>
      <td className="hidden py-2 pr-3 text-sm text-muted md:table-cell">{formatLastLogin(user.last_login_at)}</td>
      <td className="py-2 text-right">
        {user.status === 'invited' && (
          <Button variant="link" disabled={busy} onClick={() => onResendInvitation(user)}>
            Reenviar invitación
          </Button>
        )}
      </td>
    </tr>
  )
}
