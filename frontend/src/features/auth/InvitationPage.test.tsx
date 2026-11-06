import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApiError } from '@/lib/api'
import { LocationProbe } from '@/test/LocationProbe'
import { renderRoutes } from '@/test/renderWithProviders'
import * as authApi from './authApi'
import { InvitationPage } from './InvitationPage'

function renderInvitation() {
  return renderRoutes(
    [
      { path: '/invitacion/:token', element: <InvitationPage /> },
      { path: '/login', element: <LocationProbe /> },
    ],
    '/invitacion/tok-123',
  )
}

describe('InvitationPage', () => {
  it('greets the invitee and sends them to login after setting the password', async () => {
    vi.spyOn(authApi, 'fetchInvitation').mockResolvedValue({
      first_name: 'Juan',
      email: 'juan@nexosmart.com',
      company: 'NexoSmart',
    })
    const accept = vi.spyOn(authApi, 'acceptInvitation').mockResolvedValue('Listo. Ya podés ingresar.')
    renderInvitation()

    expect(await screen.findByRole('heading', { name: 'Hola Juan' })).toBeInTheDocument()
    expect(screen.getByText(/te invitaron a/i)).toHaveTextContent('NexoSmart')

    await userEvent.type(screen.getByLabelText('Nueva contraseña'), 'clave12345abc')
    await userEvent.type(screen.getByLabelText('Repetí la contraseña'), 'clave12345abc')
    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    expect(accept).toHaveBeenCalledWith('tok-123', { password: 'clave12345abc', password_confirmation: 'clave12345abc' })
    expect(await screen.findByTestId('location')).toHaveTextContent('/login | Listo. Ya podés ingresar.')
  })

  it('explains that an invalid or expired invitation cannot be used', async () => {
    vi.spyOn(authApi, 'fetchInvitation').mockRejectedValue(
      new ApiError(404, 'La invitación no es válida o venció. Pedile al administrador que te la reenvíe.'),
    )
    renderInvitation()

    expect(await screen.findByRole('alert')).toHaveTextContent('La invitación no es válida o venció')
    expect(screen.queryByLabelText('Nueva contraseña')).not.toBeInTheDocument()
  })

  it('shows the business-rule message when the invitation was used meanwhile', async () => {
    vi.spyOn(authApi, 'fetchInvitation').mockResolvedValue({ first_name: 'Juan', email: 'j@x.com', company: 'NexoSmart' })
    vi.spyOn(authApi, 'acceptInvitation').mockRejectedValue(
      new ApiError(422, 'La invitación ya no es válida.', {}, 'invitation_invalid'),
    )
    renderInvitation()

    await userEvent.type(await screen.findByLabelText('Nueva contraseña'), 'clave12345abc')
    await userEvent.type(screen.getByLabelText('Repetí la contraseña'), 'clave12345abc')
    await userEvent.click(screen.getByRole('button', { name: 'Crear cuenta' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('La invitación ya no es válida.')
  })
})
