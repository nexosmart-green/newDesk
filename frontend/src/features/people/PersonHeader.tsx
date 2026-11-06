import { useState } from 'react'
import { Avatar } from '@/components/ui/Avatar'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useCurrentUser } from '@/features/auth/useAuth'
import type { PersonProfile } from './types'
import { useSetPersonActive } from './usePeople'

export function PersonHeader({ person, canManage }: { person: PersonProfile; canManage: boolean }) {
  const { data: currentUser } = useCurrentUser()
  const [confirming, setConfirming] = useState(false)
  const setActive = useSetPersonActive(person.id)
  const isInactive = person.status === 'inactive'
  const canToggle = canManage && currentUser?.id !== person.id && person.status !== 'invited'

  function close() {
    setConfirming(false)
    setActive.reset()
  }

  return (
    <header className="flex flex-wrap items-center gap-4">
      <Avatar name={person.full_name} url={person.avatar?.profile} size="lg" />
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <h1 className="text-lg font-semibold">{person.full_name}</h1>
          {isInactive && <Badge tone="danger">De baja</Badge>}
        </div>
        <p className="text-sm text-muted">
          {[person.job_title?.name, person.area?.name].filter(Boolean).join(' · ') || 'Sin puesto asignado'}
        </p>
        <p className="break-all text-sm text-muted">{person.email}</p>
      </div>
      {canToggle && (
        <Button variant={isInactive ? 'secondary' : 'danger'} onClick={() => setConfirming(true)}>
          {isInactive ? 'Reactivar' : 'Dar de baja'}
        </Button>
      )}
      {confirming && (
        <ConfirmDialog
          title={isInactive ? `Reactivar a ${person.full_name}` : `Dar de baja a ${person.full_name}`}
          confirmLabel={isInactive ? 'Reactivar' : 'Dar de baja'}
          danger={!isInactive}
          pending={setActive.isPending}
          error={setActive.error}
          onClose={close}
          onConfirm={() => setActive.mutate(isInactive, { onSuccess: close })}
        >
          {isInactive ? (
            <p>Va a poder volver a ingresar y aparecer en los selectores de asignación.</p>
          ) : (
            <>
              <p>No va a poder ingresar ni aparecer en “Asignar a”. Sus horas y su autoría se conservan.</p>
              <p>Si tiene un cronómetro corriendo, se detiene ahora.</p>
            </>
          )}
        </ConfirmDialog>
      )}
    </header>
  )
}
