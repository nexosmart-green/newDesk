import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApiError } from '@/lib/api'
import { LocationProbe } from '@/test/LocationProbe'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as authApi from './authApi'
import { LoginPage } from './LoginPage'

function renderLogin() {
  return renderRoutes(
    [
      { path: '/login', element: <LoginPage /> },
      { path: '/', element: <LocationProbe /> },
    ],
    '/login',
  )
}

async function submitPassword() {
  await userEvent.type(screen.getByLabelText('Email'), 'ana@nexosmart.com')
  await userEvent.type(screen.getByLabelText('Contraseña'), 'secret-password-1')
  await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }))
}

describe('LoginPage', () => {
  beforeEach(() => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(null)
  })

  it('shows the backend validation message when credentials are wrong', async () => {
    vi.spyOn(authApi, 'login').mockRejectedValue(
      new ApiError(422, 'Datos inválidos', { email: ['Credenciales incorrectas.'] }),
    )
    renderLogin()

    await submitPassword()

    expect(await screen.findByRole('alert')).toHaveTextContent('Credenciales incorrectas.')
  })

  it('asks for the 2FA code and finishes the login with it', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({ status: 'two_factor_required' })
    const challenge = vi.spyOn(authApi, 'submitTwoFactorChallenge').mockResolvedValue(buildUser())
    renderLogin()

    await submitPassword()
    await userEvent.type(await screen.findByLabelText('Código de verificación'), '123456')
    await userEvent.click(screen.getByRole('button', { name: 'Verificar' }))

    expect(challenge).toHaveBeenCalledWith({ code: '123456' })
    expect(await screen.findByTestId('location')).toHaveTextContent('/')
  })

  it('accepts a recovery code instead and shows a wrong-code error inline', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({ status: 'two_factor_required' })
    const challenge = vi
      .spyOn(authApi, 'submitTwoFactorChallenge')
      .mockRejectedValue(new ApiError(422, 'Los datos enviados no son válidos.', { code: ['El código no es válido.'] }))
    renderLogin()

    await submitPassword()
    await userEvent.click(await screen.findByRole('button', { name: 'Usar código de recuperación' }))
    await userEvent.type(screen.getByLabelText('Código de recuperación'), 'abcd-efgh')
    await userEvent.click(screen.getByRole('button', { name: 'Verificar' }))

    expect(challenge).toHaveBeenCalledWith({ recovery_code: 'abcd-efgh' })
    expect(await screen.findByText('El código no es válido.')).toBeInTheDocument()
  })

  it('goes back to the password step when the pending login expired', async () => {
    vi.spyOn(authApi, 'login').mockResolvedValue({ status: 'two_factor_required' })
    vi.spyOn(authApi, 'submitTwoFactorChallenge').mockRejectedValue(
      new ApiError(401, 'La sesión de ingreso venció. Volvé a ingresar tu contraseña.'),
    )
    renderLogin()

    await submitPassword()
    await userEvent.type(await screen.findByLabelText('Código de verificación'), '123456')
    await userEvent.click(screen.getByRole('button', { name: 'Verificar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('La sesión de ingreso venció')
    expect(screen.getByLabelText('Contraseña')).toBeInTheDocument()
  })
})
