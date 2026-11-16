import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import type { Role } from './types'
import { useDeleteRole } from './useRoles'

export function DeleteRoleButton({ role, onDeleted }: { role: Role; onDeleted: () => void }) {
  const [confirming, setConfirming] = useState(false)
  const mutation = useDeleteRole()
  const inUse = role.users_count > 0

  function close() {
    setConfirming(false)
    mutation.reset()
  }

  return (
    <>
      <Button
        variant="secondary"
        onClick={() => setConfirming(true)}
        disabled={inUse}
        title={inUse ? 'Reasigná a sus usuarios antes de borrarlo.' : undefined}
      >
        Borrar rol
      </Button>
      {inUse && (
        <p className="w-full text-xs text-muted">
          Tiene {role.users_count} {role.users_count === 1 ? 'usuario' : 'usuarios'}: reasignalos para poder borrarlo.
        </p>
      )}
      {confirming && (
        <ConfirmDialog
          title={`¿Borrar «${role.name}»?`}
          confirmLabel="Borrar"
          danger
          pending={mutation.isPending}
          error={mutation.error}
          onConfirm={() => mutation.mutate(role.id, { onSuccess: onDeleted })}
          onClose={close}
        >
          <p>Nadie tiene este rol asignado, así que nadie pierde acceso. Sus permisos se borran con él y no se puede deshacer.</p>
        </ConfirmDialog>
      )}
    </>
  )
}
