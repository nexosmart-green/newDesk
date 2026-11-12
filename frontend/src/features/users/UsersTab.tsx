import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { toast } from '@/components/ui/toast/toastStore'
import { useRoles } from '@/features/roles/useRoles'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { InviteUserDialog } from './InviteUserDialog'
import { Pagination } from '@/components/ui/Pagination'
import { StaleContent } from '@/components/ui/StaleContent'
import type { UserStatus, UserSummary } from './types'
import { UserRow } from './UserRow'
import { UsersFilters } from './UsersFilters'
import { useUsers } from './useUsers'

export function UsersTab() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<UserStatus | ''>('')
  const [page, setPage] = useState(1)
  const [inviting, setInviting] = useState(false)
  const debouncedSearch = useDebouncedValue(search)

  const users = useUsers({ search: debouncedSearch, status, page })
  const roles = useRoles()
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
      {!users.data ? (
        <PageState isPending={users.isPending} error={users.error} shape="table" onRetry={() => void users.refetch()} />
      ) : (
        <StaleContent stale={users.isPlaceholderData} className="space-y-4">
          {users.data.data.length === 0 ? (
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
                  <UserRow key={user.id} user={user} roles={roles.data ?? [user.role]} />
                ))}
              </tbody>
            </table>
          )}
          <Pagination meta={users.data.meta} onPageChange={setPage} />
        </StaleContent>
      )}
      {inviting && roles.data && (
        <InviteUserDialog roles={roles.data} onClose={() => setInviting(false)} onInvited={handleInvited} />
      )}
    </div>
  )
}
