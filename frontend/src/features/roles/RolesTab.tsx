import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { useCan } from '@/features/auth/useAuth'
import { RoleEditor } from './RoleEditor'
import { usePermissionCatalog, useRoles } from './useRoles'

type Selection = { kind: 'role'; id: number } | { kind: 'new' } | null

export function RolesTab() {
  const roles = useRoles()
  const catalog = usePermissionCatalog()
  const canManage = useCan('roles.manage')
  const [selection, setSelection] = useState<Selection>(null)

  if (roles.isPending || catalog.isPending) return <p className="text-muted">Cargando…</p>
  if (roles.isError || catalog.isError) return <ErrorAlert error={roles.error ?? catalog.error} showFieldErrors />

  const selectedRole = selection?.kind === 'role' ? roles.data.find((role) => role.id === selection.id) : undefined
  const editorKey = selection?.kind === 'new' ? 'new' : selectedRole?.id

  return (
    <div className="grid gap-6 md:grid-cols-[16rem_1fr]">
      <div className="space-y-3">
        {canManage && (
          <Button variant="secondary" className="w-full" onClick={() => setSelection({ kind: 'new' })}>
            Nuevo rol
          </Button>
        )}
        <ul className="space-y-1" aria-label="Roles">
          {roles.data.map((role) => {
            const selected = selectedRole?.id === role.id
            return (
              <li key={role.id}>
                <button
                  type="button"
                  aria-current={selected ? 'true' : undefined}
                  onClick={() => setSelection({ kind: 'role', id: role.id })}
                  className={`flex w-full items-center justify-between rounded px-3 py-2 text-left text-sm ${selected ? 'bg-accent/15 text-accent' : 'hover:bg-surface'}`}
                >
                  <span>
                    {role.name}
                    {role.is_system && <span className="ml-2 text-xs text-muted">(sistema)</span>}
                  </span>
                  <span className="text-xs text-muted">{role.users_count}</span>
                </button>
              </li>
            )
          })}
        </ul>
      </div>
      <div className="rounded-lg border border-border bg-surface p-4">
        {editorKey === undefined ? (
          <p className="text-sm text-muted">Elegí un rol para ver sus permisos.</p>
        ) : (
          <RoleEditor
            key={editorKey}
            role={selectedRole ?? null}
            catalog={catalog.data}
            canManage={canManage}
            onSaved={(role) => setSelection({ kind: 'role', id: role.id })}
            onDeleted={() => setSelection(null)}
          />
        )}
      </div>
    </div>
  )
}
