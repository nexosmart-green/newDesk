import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApiError } from '@/lib/api'
import { renderAt } from '@/test/renderWithProviders'
import { RoleEditor } from './RoleEditor'
import * as rolesApi from './rolesApi'
import type { PermissionOption, Role } from './types'

const catalog: PermissionOption[] = [
  { value: 'module.projects', label: 'Módulo Proyectos y tareas' },
  { value: 'module.time', label: 'Módulo Horas y cronómetro' },
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

  it('shows module switches first and sends them with the permissions', async () => {
    const update = vi.spyOn(rolesApi, 'updateRole').mockResolvedValue(role)
    renderEditor({ role: { ...role, permissions: ['module.projects', 'projects.manage'] } })

    const modules = screen.getByRole('region', { name: 'Módulos' })
    const projects = within(modules).getByRole('switch', { name: 'Proyectos y tareas' })
    const time = within(modules).getByRole('switch', { name: 'Horas y cronómetro' })
    expect(projects).toHaveAttribute('aria-checked', 'true')
    expect(time).toHaveAttribute('aria-checked', 'false')
    // Module switches are not repeated as plain permission checkboxes.
    expect(screen.queryByRole('checkbox', { name: /Módulo/ })).not.toBeInTheDocument()

    await userEvent.click(time)
    await userEvent.click(projects)
    expect(time).toHaveAttribute('aria-checked', 'true')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar rol' }))

    expect(update).toHaveBeenCalledWith(7, { name: 'PM', permissions: ['module.time', 'projects.manage'] })
    expect(await screen.findByText('Rol «PM» guardado.')).toBeInTheDocument()
  })

  it('shows the system Admin with every module on, read-only', () => {
    renderEditor({ role: { ...role, is_system: true, name: 'Admin', permissions: [] } })

    for (const moduleSwitch of screen.getAllByRole('switch')) {
      expect(moduleSwitch).toHaveAttribute('aria-checked', 'true')
      expect(moduleSwitch).toBeDisabled()
    }
  })

  it('shows the backend business rule as a toast', async () => {
    vi.spyOn(rolesApi, 'updateRole').mockRejectedValue(
      new ApiError(422, 'El rol del sistema no se puede modificar.', {}, 'system_role_locked'),
    )
    renderEditor()

    await userEvent.click(screen.getByRole('button', { name: 'Guardar rol' }))

    expect(await screen.findByText('El rol del sistema no se puede modificar.')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('asks for confirmation before deleting an unused role', async () => {
    const remove = vi.spyOn(rolesApi, 'deleteRole').mockResolvedValue('Rol borrado.')
    renderEditor({ role: { ...role, users_count: 0 } })

    await userEvent.click(screen.getByRole('button', { name: 'Borrar rol' }))
    expect(screen.getByRole('dialog', { name: '¿Borrar «PM»?' })).toHaveTextContent('Nadie tiene este rol asignado')
    await userEvent.click(screen.getByRole('button', { name: 'Borrar' }))

    expect(remove).toHaveBeenCalledWith(7)
  })
})
