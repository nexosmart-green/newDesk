import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { Role } from '@/features/roles/types'
import { ApiError } from '@/lib/api'
import { renderAt } from '@/test/renderWithProviders'
import { InviteUserDialog } from './InviteUserDialog'
import * as usersApi from './usersApi'

const roles: Role[] = [
  { id: 1, name: 'Admin', is_system: true, permissions: [], users_count: 1 },
  { id: 2, name: 'Colaborador', is_system: false, permissions: [], users_count: 3 },
]

async function fillForm() {
  await userEvent.type(screen.getByLabelText('Nombre'), 'Juan')
  await userEvent.type(screen.getByLabelText('Apellido'), 'Pérez')
  await userEvent.type(screen.getByLabelText('Email'), 'juan@nexosmart.com')
  await userEvent.selectOptions(screen.getByLabelText('Rol'), 'Colaborador')
  await userEvent.click(screen.getByRole('button', { name: 'Enviar invitación' }))
}

describe('InviteUserDialog', () => {
  it('shows the backend field errors next to each field', async () => {
    vi.spyOn(usersApi, 'inviteUser').mockRejectedValue(
      new ApiError(422, 'Los datos enviados no son válidos.', {
        email: ['El email ya está en uso.'],
        last_name: ['El apellido es obligatorio.'],
      }),
    )
    renderAt(<InviteUserDialog roles={roles} onClose={vi.fn()} onInvited={vi.fn()} />)

    await fillForm()

    expect(await screen.findByText('El email ya está en uso.')).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('El apellido es obligatorio.')).toBeInTheDocument()
  })

  it('sends the invitation with the chosen role', async () => {
    const invite = vi.spyOn(usersApi, 'inviteUser').mockResolvedValue({
      id: 5,
      first_name: 'Juan',
      last_name: 'Pérez',
      full_name: 'Juan Pérez',
      email: 'juan@nexosmart.com',
      status: 'invited',
      role: { id: 2, name: 'Colaborador' },
      last_login_at: null,
    })
    const onInvited = vi.fn()
    renderAt(<InviteUserDialog roles={roles} onClose={vi.fn()} onInvited={onInvited} />)

    await fillForm()

    expect(invite).toHaveBeenCalledWith(
      { first_name: 'Juan', last_name: 'Pérez', email: 'juan@nexosmart.com', role_id: 2 },
    )
    await vi.waitFor(() => expect(onInvited).toHaveBeenCalled())
  })
})
