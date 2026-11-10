import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { TextField } from '@/components/ui/TextField'
import { isModulePermission } from '@/features/auth/modules'
import { fieldErrorOf } from '@/lib/api'
import { DeleteRoleButton } from './DeleteRoleButton'
import { ModuleSwitches } from './ModuleSwitches'
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
  const isSystem = role?.is_system ?? false
  const readOnly = !canManage || isSystem
  const modules = catalog.filter((option) => isModulePermission(option.value))
  const fineGrained = catalog.filter((option) => !isModulePermission(option.value))
  // The system Admin always has every module and permission, whatever its stored list says.
  const shown = isSystem ? catalog.map((option) => option.value) : permissions

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
    <form onSubmit={handleSubmit} className="space-y-6" aria-label={role ? `Editar rol ${role.name}` : 'Nuevo rol'}>
      {isSystem && (
        <p className="rounded-lg bg-surface-2 px-3 py-2 text-sm text-muted">
          Es el rol del sistema: tiene todos los módulos y permisos, y no se puede modificar.
        </p>
      )}
      <TextField
        label="Nombre del rol"
        required
        maxLength={60}
        disabled={readOnly}
        value={name}
        error={fieldErrorOf(mutation.error, 'name')}
        onChange={(event) => setName(event.target.value)}
        className="sm:max-w-sm"
      />
      {modules.length > 0 && <ModuleSwitches modules={modules} enabled={shown} disabled={readOnly} onToggle={togglePermission} />}
      <fieldset disabled={readOnly} className="space-y-2">
        <legend className="text-sm font-semibold">Permisos</legend>
        <p className="text-xs text-muted">Qué puede hacer dentro de los módulos que tiene.</p>
        <div className="grid gap-2 sm:grid-cols-2">
          {fineGrained.map((option) => (
            <label
              key={option.value}
              className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-2 text-sm transition-colors hover:bg-surface-2 has-[:checked]:border-brand/50 has-[:disabled]:cursor-not-allowed"
            >
              <input
                type="checkbox"
                className="size-4 shrink-0 accent-brand-strong"
                checked={shown.includes(option.value)}
                onChange={(event) => togglePermission(option.value, event.target.checked)}
              />
              {option.label}
            </label>
          ))}
        </div>
        {permissionsError && <p className="text-sm text-danger">{permissionsError}</p>}
      </fieldset>
      {!readOnly && (
        <div className="flex flex-wrap gap-2 border-t border-border pt-4">
          <Button type="submit" loading={mutation.isPending}>
            {role ? 'Guardar rol' : 'Crear rol'}
          </Button>
          {role && <DeleteRoleButton role={role} onDeleted={onDeleted} />}
        </div>
      )}
    </form>
  )
}
