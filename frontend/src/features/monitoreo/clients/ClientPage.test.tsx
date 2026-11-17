import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ClientPage } from './ClientPage'
import { ProjectAdminPage } from './ProjectAdminPage'

vi.mock('uplot', () => ({ default: class {} }))

const formPanel = async () => (await screen.findByRole('heading', { name: 'Datos del proyecto' })).closest('section') as HTMLElement

afterEach(() => vi.restoreAllMocks())

// Client and project (name, code, status, create/delete) are newDesk's: edited in Clientes and Proyectos.
describe('cliente y proyecto de newDesk', () => {
  it('el cliente se edita en Clientes: acá quedan sus proyectos y los usuarios del portal', async () => {
    mockPortalAdmin()
    renderWithProviders(
      <Routes>
        <Route path="/monitoreo/clientes/:id" element={<ClientPage />} />
      </Routes>,
      { route: '/monitoreo/clientes/1', admin: true },
    )
    expect(await screen.findByRole('heading', { name: 'Playinn', level: 1 })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Editar en Clientes' })).toHaveAttribute('href', '/clientes/1')
    expect(screen.getByRole('link', { name: 'Playinn' })).toHaveAttribute('href', '/monitoreo/clientes/1/proyectos/1')
    for (const name of ['Renombrar', 'Desactivar', 'Eliminar cliente', 'Crear proyecto']) {
      expect(screen.queryByRole('button', { name })).not.toBeInTheDocument()
    }
    expect(screen.getByRole('button', { name: 'Agregar usuario' })).toBeInTheDocument()
  })

  it('muestra el nombre y el código del proyecto sin editarlos y no los manda al guardar', async () => {
    const user = userEvent.setup()
    const { api } = mockPortalAdmin()
    renderWithProviders(
      <Routes>
        <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
      </Routes>,
      { route: '/monitoreo/clientes/1/proyectos/1', admin: true },
    )
    const panel = await formPanel()
    expect(within(panel).getByText('playinn')).toBeInTheDocument()
    expect(within(panel).getByRole('link', { name: 'Editar nombre y código en Proyectos' })).toHaveAttribute('href', '/proyectos/1')
    expect(within(panel).queryByLabelText('Nombre')).not.toBeInTheDocument()
    expect(within(panel).queryByLabelText('Código')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Eliminar proyecto' })).not.toBeInTheDocument()

    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))
    expect(await within(panel).findByText('Guardado.')).toBeInTheDocument()
    const body = api.put.mock.calls[0]?.[1]
    expect(body).toMatchObject({ slug: 'playinn' })
    expect(body).not.toHaveProperty('name')
    expect(body).not.toHaveProperty('code_prefix')
  })
})
