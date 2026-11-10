import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApiError } from '@/lib/api'
import { renderAt } from '@/test/renderWithProviders'
import { RoleEditor } from './RoleEditor'
import * as rolesApi from './rolesApi'
import type { PermissionOption, Role } from './types'

const catalog: PermissionOption[] = [
  { value: 'clients.manage', label: 'Gestionar clientes' },
  { value: 'projects.manage', label: 'Gestionar proyectos' },
  { value: 'time.track', label: 'Cronometrar' },
]

const role: Role = { id: 7, name: 'PM', is_system: false, permissions: ['projects.manage'], users_count: 2 }

function renderEditor(overrides: Partial<Parameters<typeof RoleEditor>[0]> = {}) {
  const onSaved = vi.fn()
  renderAt(<RoleEditor role={role} catalog={catalog} canManage onSaved={onSaved} onDeleted={vi.fn()} {...overrides} />)
  return { onSaved }
}

describe('RoleEditor', () => {
  it('submits the toggled permissions in catalog order', async () => {
    const update = vi.spyOn(rolesApi, 'updateRole').mockResolvedValue({ ...role, name: 'Project Manager' })
    const { onSaved } = renderEditor()

    await userEvent.click(screen.getByLabelText('Cronometrar'))
    await userEvent.click(screen.getByLabelText('Gestionar proyectos'))
    await userEvent.click(screen.getByLabelText('Gestionar clientes'))
    await userEvent.clear(screen.getByLabelText('Nombre del rol'))
    await userEvent.type(screen.getByLabelText('Nombre del rol'), 'Project Manager')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar rol' }))

    expect(update).toHaveBeenCalledWith(7, { name: 'Project Manager', permissions: ['clients.manage', 'time.track'] })
    await vi.waitFor(() => expect(onSaved).toHaveBeenCalled())
  })

  it('creates a new role', async () => {
    const create = vi.spyOn(rolesApi, 'createRole').mockResolvedValue({ ...role, id: 9 })
    renderEditor({ role: null })

    await userEvent.type(screen.getByLabelText('Nombre del rol'), 'Ventas')
    await userEvent.click(screen.getByLabelText('Gestionar clientes'))
    await userEvent.click(screen.getByRole('button', { name: 'Crear rol' }))

    expect(create).toHaveBeenCalledWith({ name: 'Ventas', permissions: ['clients.manage'] })
  })

  it('shows the system role read-only', () => {
    renderEditor({ role: { ...role, is_system: true, name: 'Admin' } })

    expect(screen.getByLabelText('Nombre del rol')).toBeDisabled()
    expect(screen.getByLabelText('Cronometrar')).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Guardar rol' })).not.toBeInTheDocument()
  })

  it('shows the backend business rule as an alert', async () => {
    vi.spyOn(rolesApi, 'updateRole').mockRejectedValue(
      new ApiError(422, 'El rol del sistema no se puede modificar.', {}, 'system_role_locked'),
    )
    renderEditor()

    await userEvent.click(screen.getByRole('button', { name: 'Guardar rol' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('El rol del sistema no se puede modificar.')
  })

  it('asks for confirmation before deleting an unused role', async () => {
    const remove = vi.spyOn(rolesApi, 'deleteRole').mockResolvedValue('Rol borrado.')
    renderEditor({ role: { ...role, users_count: 0 } })

    await userEvent.click(screen.getByRole('button', { name: 'Borrar rol' }))
    expect(screen.getByRole('dialog', { name: '¿Borrar el rol PM?' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Borrar' }))

    expect(remove).toHaveBeenCalledWith(7)
  })
})
