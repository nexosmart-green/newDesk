import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
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
        <Dialog title={`¿Borrar el rol ${role.name}?`} size="sm" onClose={close}>
          <p className="text-sm text-muted">Esta acción no se puede deshacer.</p>
          <FieldErrorsAlert error={mutation.error} />
          <DialogFooter>
            <Button variant="secondary" onClick={close} data-autofocus>
              Cancelar
            </Button>
            <Button
              variant="danger"
              loading={mutation.isPending}
              onClick={() => mutation.mutate(role.id, { onSuccess: onDeleted })}
            >
              Borrar
            </Button>
          </DialogFooter>
        </Dialog>
      )}
    </>
  )
}
