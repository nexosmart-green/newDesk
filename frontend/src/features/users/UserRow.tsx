import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { toast } from '@/components/ui/toast/toastStore'
import { userStatusLabels, type UserSummary } from './types'
import { useChangeUserRole, useResendInvitation } from './useUsers'

interface UserRowProps {
  user: UserSummary
  roles: Pick<UserSummary['role'], 'id' | 'name'>[]
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

/** Each row saves on its own: changing one person's role only locks that row. */
export function UserRow({ user, roles }: UserRowProps) {
  const changeRole = useChangeUserRole()
  const resend = useResendInvitation()
  const busy = changeRole.isPending || resend.isPending

  function handleChangeRole(roleId: number) {
    resend.reset()
    changeRole.mutate({ userId: user.id, roleId }, { onSuccess: () => toast.success(`Rol de ${user.full_name} actualizado.`) })
  }

  function handleResend() {
    changeRole.reset()
    resend.mutate(user.id, { onSuccess: (message) => toast.success(message ?? `Invitación reenviada a ${user.email}.`) })
  }

  return (
    <tr className="border-t border-border align-top" aria-busy={busy || undefined}>
      <td className="py-2 pr-3">
        <div className="font-medium">{user.full_name}</div>
        <div className="break-all text-xs text-muted">{user.email}</div>
      </td>
      <td className="space-y-1 py-2 pr-3">
        <select
          aria-label={`Rol de ${user.full_name}`}
          value={user.role.id}
          disabled={busy}
          onChange={(event) => handleChangeRole(Number(event.target.value))}
          className="w-full min-w-28 rounded border border-border bg-bg px-2 py-1 text-sm disabled:opacity-60"
        >
          {roles.map((role) => (
            <option key={role.id} value={role.id}>
              {role.name}
            </option>
          ))}
        </select>
        <FieldErrorsAlert error={changeRole.error ?? resend.error} />
      </td>
      <td className={`hidden py-2 pr-3 text-sm sm:table-cell ${statusClassNames[user.status]}`}>
        {userStatusLabels[user.status]}
      </td>
      <td className="hidden py-2 pr-3 text-sm text-muted md:table-cell">{formatLastLogin(user.last_login_at)}</td>
      <td className="py-2 text-right">
        {user.status === 'invited' && (
          <Button variant="link" disabled={changeRole.isPending} loading={resend.isPending} onClick={handleResend}>
            Reenviar invitación
          </Button>
        )}
      </td>
    </tr>
  )
}
