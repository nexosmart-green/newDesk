import { act, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { currentUserKey } from '@/features/auth/useAuth'
import { MOCK_CODE, mockClientPortal } from '../../test/clientPortalApi'
import { playinnUser } from '../../test/fixtures/clientPortal'
import { renderWithProviders } from '../../test/render'
import { RequireClient } from '../PortalPages'
import { PortalLoginPage } from './PortalLoginPage'

// PortalPages trae la página de estado, con uPlot (que pide matchMedia al cargarse); acá no se dibuja ningún gráfico.
vi.mock('uplot', () => ({ default: class {} }))

function renderLogin(route = '/portal/login') {
  return renderWithProviders(
    <Routes>
      <Route path="/portal/login" element={<PortalLoginPage />} />
      <Route
        path="/portal/*"
        element={
          <RequireClient>
            <div>portal del cliente</div>
          </RequireClient>
        }
      />
      <Route
        path="/portal/:slug/reportes"
        element={
          <RequireClient>
            <div>reportes del proyecto</div>
          </RequireClient>
        }
      />
    </Routes>,
    { route },
  )
}

async function passEmailAndCode(user: ReturnType<typeof userEvent.setup>, email: string, code = MOCK_CODE) {
  await user.type(screen.getByLabelText('Mail'), email)
  await user.click(screen.getByRole('button', { name: 'Enviar código' }))
  const codeInput = await screen.findByLabelText('Código de 6 dígitos')
  await user.type(codeInput, code)
  await user.click(screen.getByRole('button', { name: 'Continuar' }))
}

afterEach(() => vi.restoreAllMocks())

describe('login del cliente', () => {
  it('entra con mail y código, sin autenticador, a su portal', async () => {
    const user = userEvent.setup()
    const { state, api, csrf } = mockClientPortal(null)
    renderLogin()

    // Antes de enviar no hay campo de código.
    expect(screen.queryByLabelText('Código de 6 dígitos')).not.toBeInTheDocument()
    await user.type(screen.getByLabelText('Mail'), ' Cliente@Playinn.com ')
    await user.click(screen.getByRole('button', { name: 'Enviar código' }))
    expect(await screen.findByText('Si el mail está habilitado, te enviamos un código.')).toBeInTheDocument()
    state.user = playinnUser // el back abre la sesión al verificar
    await user.type(screen.getByLabelText('Código de 6 dígitos'), MOCK_CODE)
    await user.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(await screen.findByText('portal del cliente')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Autenticador' })).not.toBeInTheDocument()
    // La sesión de Sanctum existe antes de pedir el código: solo vale en el navegador que lo pidió.
    expect(csrf).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true })
    expect(api.post).toHaveBeenCalledWith('/portal/auth/request-code', { email: 'cliente@playinn.com' })
    expect(api.post).toHaveBeenCalledWith('/portal/auth/verify-code', { email: 'cliente@playinn.com', code: MOCK_CODE })
  })

  it('bloquea el reenvío durante el cooldown y deja cambiar el mail', async () => {
    const user = userEvent.setup()
    mockClientPortal(null)
    renderLogin()
    await user.type(screen.getByLabelText('Mail'), 'a@b.com')
    await user.click(screen.getByRole('button', { name: 'Enviar código' }))
    const resend = await screen.findByRole('button', { name: /Reenviar código en \d+ s/ })
    expect(resend).toBeDisabled()
    expect(screen.getByLabelText('Mail')).toBeDisabled()

    await user.click(screen.getByRole('button', { name: 'Cambiar mail' }))
    expect(screen.getByLabelText('Mail')).toBeEnabled()
    expect(screen.queryByLabelText('Código de 6 dígitos')).not.toBeInTheDocument()
  })

  it('muestra el error del backend si el código es incorrecto', async () => {
    const user = userEvent.setup()
    mockClientPortal(null)
    renderLogin()
    await passEmailAndCode(user, 'a@b.com', '999999')
    expect(await screen.findByRole('alert')).toHaveTextContent('Código inválido o vencido.')
  })

  it('vuelve a la página del portal desde la que lo mandaron a ingresar', async () => {
    const user = userEvent.setup()
    const { state } = mockClientPortal(null)
    renderLogin('/portal/playinn/reportes')
    // Sin sesión, el guard lo manda al login.
    await screen.findByLabelText('Mail')
    state.user = playinnUser
    await passEmailAndCode(user, 'cliente@playinn.com')
    expect(await screen.findByText('reportes del proyecto')).toBeInTheDocument()
  })
})

// Una pestaña abierta cuando la sesión vence guarda el /me bueno de antes y el 401 nuevo. Al ingresar de nuevo tiene
// que entrar sin recargar: el guard espera el /me nuevo en vez de releer el 401.
describe('login con un 401 guardado', () => {
  it('después del código entra al portal sin recargar', async () => {
    const user = userEvent.setup()
    const { state } = mockClientPortal(playinnUser)
    const { client } = renderLogin('/portal')
    expect(await screen.findByText('portal del cliente')).toBeInTheDocument()

    // La sesión vence: el /me siguiente da 401 y el guard manda al login.
    state.user = null
    await act(() => client.refetchQueries({ queryKey: currentUserKey }))
    await screen.findByLabelText('Mail')

    state.user = playinnUser
    await passEmailAndCode(user, 'cliente@playinn.com')
    expect(await screen.findByText('portal del cliente')).toBeInTheDocument()
  })
})
