import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
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
        <Dialog title={`¿Borrar el rol ${role.name}?`} onClose={close}>
          <p className="text-sm text-muted">Esta acción no se puede deshacer.</p>
          <ErrorAlert error={mutation.error} showFieldErrors />
          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={close}>
              Cancelar
            </Button>
            <Button
              variant="danger"
              disabled={mutation.isPending}
              onClick={() => mutation.mutate(role.id, { onSuccess: onDeleted })}
            >
              {mutation.isPending ? 'Borrando…' : 'Borrar'}
            </Button>
          </div>
        </Dialog>
      )}
    </>
  )
}
