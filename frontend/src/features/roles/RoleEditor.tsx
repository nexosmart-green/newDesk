import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { DeleteRoleButton } from './DeleteRoleButton'
import type { PermissionOption, Role } from './types'
import { useSaveRole } from './useRoles'

interface RoleEditorProps {
  /** `null` creates a new role. */
  role: Role | null
  catalog: PermissionOption[]
  canManage: boolean
  onSaved: (role: Role) => void
  onDeleted: () => void
}

export function RoleEditor({ role, catalog, canManage, onSaved, onDeleted }: RoleEditorProps) {
  const [name, setName] = useState(role?.name ?? '')
  const [permissions, setPermissions] = useState<string[]>(role?.permissions ?? [])
  const mutation = useSaveRole(role?.id ?? null)
  const readOnly = !canManage || (role?.is_system ?? false)

  function togglePermission(value: string, checked: boolean) {
    setPermissions((current) => (checked ? [...current, value] : current.filter((item) => item !== value)))
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    // Keep catalog order so the payload is stable regardless of click order.
    const ordered = catalog.map((option) => option.value).filter((value) => permissions.includes(value))
    mutation.mutate({ name: name.trim(), permissions: ordered }, { onSuccess: onSaved })
  }

  const permissionsError = fieldErrorOf(mutation.error, 'permissions') ?? fieldErrorOf(mutation.error, 'permissions.0')

  return (
    <form onSubmit={handleSubmit} className="space-y-4" aria-label={role ? `Editar rol ${role.name}` : 'Nuevo rol'}>
      {role?.is_system && (
        <p className="text-sm text-muted">Es el rol del sistema: tiene todos los permisos y no se puede modificar.</p>
      )}
      <TextField
        label="Nombre del rol"
        required
        maxLength={60}
        disabled={readOnly}
        value={name}
        error={fieldErrorOf(mutation.error, 'name')}
        onChange={(event) => setName(event.target.value)}
      />
      <fieldset disabled={readOnly} className="space-y-2">
        <legend className="mb-1 text-sm text-muted">Permisos</legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {catalog.map((option) => (
            <label key={option.value} className="flex items-start gap-2 rounded border border-border px-3 py-2 text-sm">
              <input
                type="checkbox"
                className="mt-0.5"
                checked={permissions.includes(option.value)}
                onChange={(event) => togglePermission(option.value, event.target.checked)}
              />
              {option.label}
            </label>
          ))}
        </div>
        {permissionsError && <p className="text-sm text-danger">{permissionsError}</p>}
      </fieldset>
      <ErrorAlert error={mutation.error} />
      {!readOnly && (
        <div className="flex flex-wrap gap-2">
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Guardando…' : role ? 'Guardar rol' : 'Crear rol'}
          </Button>
          {role && <DeleteRoleButton role={role} onDeleted={onDeleted} />}
        </div>
      )}
    </form>
  )
}
