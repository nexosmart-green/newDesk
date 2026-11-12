import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { ApiError } from '@/lib/api'
import { ClientFormDialog } from './ClientFormDialog'
import type { Client } from './types'
import { useChangeClientStatus, useDeleteClient } from './useClients'

type OpenDialog = 'edit' | 'delete' | null

/** CA-02.01: a client with projects is not deleted, it is inactivated — offered right in the error. */
export function ClientActions({ client }: { client: Client }) {
  const navigate = useNavigate()
  const [open, setOpen] = useState<OpenDialog>(null)
  const changeStatus = useChangeClientStatus(client.id)
  const remove = useDeleteClient(client.id)
  const isActive = client.status === 'active'
  const hasProjects = remove.error instanceof ApiError && remove.error.code === 'client_has_projects'

  function closeDelete() {
    setOpen(null)
    remove.reset()
  }

  return (
    <div className="flex flex-wrap gap-2">
      <Button variant="secondary" onClick={() => setOpen('edit')}>
        Editar
      </Button>
      <Button
        variant="secondary"
        loading={changeStatus.isPending}
        onClick={() => changeStatus.mutate(isActive ? 'inactive' : 'active')}
      >
        {isActive ? 'Inactivar' : 'Activar'}
      </Button>
      <Button variant="danger" onClick={() => setOpen('delete')}>
        Borrar
      </Button>
      <FieldErrorsAlert error={changeStatus.error} />
      {open === 'edit' && <ClientFormDialog client={client} onClose={() => setOpen(null)} onSaved={() => setOpen(null)} />}
      {open === 'delete' && (
        <ConfirmDialog
          title={`¿Borrar «${client.display_name}»?`}
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onClose={closeDelete}
          onConfirm={() => remove.mutate(undefined, { onSuccess: () => navigate('/clientes', { replace: true }) })}
        >
          <p>Se pierden sus datos fiscales, de contacto y las notas. No se puede deshacer.</p>
          {hasProjects && (
            <p className="text-muted">Tiene proyectos, así que no se puede borrar.{isActive && ' Podés inactivarlo para que no se ofrezca en proyectos nuevos.'}</p>
          )}
          {hasProjects && isActive && (
            <Button
              variant="secondary"
              loading={changeStatus.isPending}
              onClick={() => changeStatus.mutate('inactive', { onSuccess: closeDelete })}
            >
              Inactivar en su lugar
            </Button>
          )}
        </ConfirmDialog>
      )}
    </div>
  )
}
