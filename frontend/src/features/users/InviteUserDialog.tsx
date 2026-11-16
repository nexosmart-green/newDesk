import { useId, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { inputClassName, TextField } from '@/components/ui/TextField'
import type { Role } from '@/features/roles/types'
import { fieldErrorOf } from '@/lib/api'
import { hasChanges } from '@/lib/formState'
import type { UserSummary } from './types'
import { useInviteUser } from './useUsers'

interface InviteUserDialogProps {
  roles: Role[]
  onClose: () => void
  onInvited: (user: UserSummary) => void
}

const emptyInvite = { first_name: '', last_name: '', email: '', role_id: '' }

export function InviteUserDialog({ roles, onClose, onInvited }: InviteUserDialogProps) {
  const [form, setForm] = useState(emptyInvite)
  const mutation = useInviteUser()
  const roleSelectId = useId()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate({ ...form, role_id: Number(form.role_id) }, { onSuccess: onInvited })
  }

  const roleError = fieldErrorOf(mutation.error, 'role_id')

  return (
    <Dialog title="Invitar usuario" description="Le llega un email para crear su contraseña." size="md" dirty={hasChanges(emptyInvite, form)} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            label="Nombre"
            required
            autoComplete="off"
            value={form.first_name}
            error={fieldErrorOf(mutation.error, 'first_name')}
            onChange={(event) => setForm({ ...form, first_name: event.target.value })}
          />
          <TextField
            label="Apellido"
            required
            autoComplete="off"
            value={form.last_name}
            error={fieldErrorOf(mutation.error, 'last_name')}
            onChange={(event) => setForm({ ...form, last_name: event.target.value })}
          />
        </div>
        <TextField
          label="Email"
          type="email"
          required
          autoComplete="off"
          value={form.email}
          error={fieldErrorOf(mutation.error, 'email')}
          onChange={(event) => setForm({ ...form, email: event.target.value })}
        />
        <div className="space-y-1">
          <label htmlFor={roleSelectId} className="block text-sm text-muted">
            Rol
          </label>
          <select
            id={roleSelectId}
            required
            value={form.role_id}
            aria-invalid={roleError ? true : undefined}
            onChange={(event) => setForm({ ...form, role_id: event.target.value })}
            className={inputClassName}
          >
            <option value="">Elegí un rol</option>
            {roles.map((role) => (
              <option key={role.id} value={role.id}>
                {role.name}
              </option>
            ))}
          </select>
          {roleError && <p className="text-sm text-danger">{roleError}</p>}
        </div>
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={mutation.isPending}>
            Enviar invitación
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
