import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { PageState } from '@/components/ui/PageState'
import { toast } from '@/components/ui/toast/toastStore'
import { useRoles } from '@/features/roles/useRoles'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { InviteUserDialog } from './InviteUserDialog'
import { Pagination } from '@/components/ui/Pagination'
import type { UserStatus, UserSummary } from './types'
import { UserRow } from './UserRow'
import { UsersFilters } from './UsersFilters'
import { useChangeUserRole, useResendInvitation, useUsers } from './useUsers'

export function UsersTab() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<UserStatus | ''>('')
  const [page, setPage] = useState(1)
  const [inviting, setInviting] = useState(false)
  const debouncedSearch = useDebouncedValue(search)

  const users = useUsers({ search: debouncedSearch, status, page })
  const roles = useRoles()
  const changeRole = useChangeUserRole()
  const resend = useResendInvitation()
  const actionError = changeRole.error ?? resend.error

  function resetFeedback() {
    changeRole.reset()
    resend.reset()
  }

  function handleChangeRole(user: UserSummary, roleId: number) {
    resetFeedback()
    changeRole.mutate({ userId: user.id, roleId }, { onSuccess: () => toast.success(`Rol de ${user.full_name} actualizado.`) })
  }

  function handleResend(user: UserSummary) {
    resetFeedback()
    resend.mutate(user.id, { onSuccess: (message) => toast.success(message ?? `Invitación reenviada a ${user.email}.`) })
  }

  function handleInvited(user: UserSummary) {
    setInviting(false)
    toast.success(`Invitación enviada a ${user.email}.`)
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
        <UsersFilters
          search={search}
          status={status}
          onSearchChange={(value) => {
            setSearch(value)
            setPage(1)
          }}
          onStatusChange={(value) => {
            setStatus(value)
            setPage(1)
          }}
        />
        <Button onClick={() => setInviting(true)} disabled={!roles.data}>
          Invitar usuario
        </Button>
      </div>
      <FieldErrorsAlert error={actionError} />
      {!users.data ? (
        <PageState isPending={users.isPending} error={users.error} shape="table" onRetry={() => void users.refetch()} />
      ) : users.data.data.length === 0 ? (
        <p className="text-sm text-muted">No hay usuarios que coincidan con la búsqueda.</p>
      ) : (
        <table className="w-full text-left">
          <thead className="text-xs text-muted">
            <tr>
              <th className="pb-2 font-normal">Usuario</th>
              <th className="pb-2 font-normal">Rol</th>
              <th className="hidden pb-2 font-normal sm:table-cell">Estado</th>
              <th className="hidden pb-2 font-normal md:table-cell">Último ingreso</th>
              <th className="pb-2 font-normal">
                <span className="sr-only">Acciones</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {users.data.data.map((user) => (
              <UserRow
                key={user.id}
                user={user}
                roles={roles.data ?? [user.role]}
                onChangeRole={handleChangeRole}
                onResendInvitation={handleResend}
                busy={changeRole.isPending || resend.isPending}
              />
            ))}
          </tbody>
        </table>
      )}
      {users.data && <Pagination meta={users.data.meta} onPageChange={setPage} />}
      {inviting && roles.data && (
        <InviteUserDialog roles={roles.data} onClose={() => setInviting(false)} onInvited={handleInvited} />
      )}
    </div>
  )
}
