import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { ApiError } from '@/lib/api'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import * as securityApi from './securityApi'
import { SecurityPage } from './SecurityPage'

const setup = { secret: 'JBSWY3DPEHPK3PXP', otpauth_url: 'otpauth://totp/newDesk:ana@nexosmart.com?secret=JBSWY3DPEHPK3PXP' }

describe('SecurityPage', () => {
  it('walks through the 2FA setup and shows the recovery codes once', async () => {
    const fetchMe = vi
      .spyOn(authApi, 'fetchCurrentUser')
      .mockResolvedValue(buildUser({ two_factor: { required: true, enabled: false } }))
    vi.spyOn(securityApi, 'startTwoFactorSetup').mockResolvedValue(setup)
    const confirm = vi.spyOn(securityApi, 'confirmTwoFactor').mockResolvedValue(['aaaa-1111', 'bbbb-2222'])
    renderAt(<SecurityPage />)

    expect(await screen.findByRole('alert')).toHaveTextContent('Tu rol exige verificación en dos pasos')
    await userEvent.click(screen.getByRole('button', { name: 'Activar verificación en dos pasos' }))

    expect(await screen.findByTestId('two-factor-secret')).toHaveTextContent(setup.secret)
    expect(await screen.findByRole('img', { name: /código qr/i })).toBeInTheDocument()

    await userEvent.type(screen.getByLabelText('Código de verificación'), '654321')
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar' }))

    expect(confirm).toHaveBeenCalledWith('654321')
    const codes = await screen.findByRole('list', { name: 'Códigos de recuperación' })
    expect(within(codes).getAllByRole('listitem').map((item) => item.textContent)).toEqual(['aaaa-1111', 'bbbb-2222'])

    // Acknowledging is required before leaving; then /me is refreshed.
    const done = screen.getByRole('button', { name: 'Listo' })
    expect(done).toBeDisabled()
    fetchMe.mockResolvedValue(buildUser({ two_factor: { required: true, enabled: true } }))
    await userEvent.click(screen.getByLabelText('Ya guardé los códigos'))
    await userEvent.click(done)

    expect(await screen.findByText(/no se puede desactivar/)).toBeInTheDocument()
    expect(screen.queryByText('aaaa-1111')).not.toBeInTheDocument()
  })

  it('shows the business-rule message when the code is wrong', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
    vi.spyOn(securityApi, 'startTwoFactorSetup').mockResolvedValue(setup)
    vi.spyOn(securityApi, 'confirmTwoFactor').mockRejectedValue(
      new ApiError(422, 'El código no es válido. Revisá la hora del celular y probá de nuevo.', {}, 'two_factor_invalid_code'),
    )
    renderAt(<SecurityPage />)

    await userEvent.click(await screen.findByRole('button', { name: 'Activar verificación en dos pasos' }))
    await userEvent.type(await screen.findByLabelText('Código de verificación'), '000000')
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('El código no es válido')
  })

  it('lets a non-admin disable 2FA with the password', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ two_factor: { required: false, enabled: true } }))
    const disable = vi.spyOn(securityApi, 'disableTwoFactor').mockResolvedValue('Verificación en dos pasos desactivada.')
    renderAt(<SecurityPage />)

    await userEvent.type(await screen.findByLabelText('Contraseña actual'), 'my-password-1')
    await userEvent.click(screen.getByRole('button', { name: 'Desactivar verificación en dos pasos' }))

    expect(disable).toHaveBeenCalledWith('my-password-1')
  })
})
