import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ProjectsPage } from './ProjectsPage'

// uPlot pide matchMedia al cargarse y jsdom no lo trae; acá no se dibuja ningún gráfico.
vi.mock('uplot', () => ({ default: class {} }))

function renderProjects() {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/proyectos" element={<ProjectsPage />} />
    </Routes>,
    { route: '/monitoreo/proyectos' },
  )
}

afterEach(() => vi.restoreAllMocks())

describe('listado de proyectos del equipo (D-048)', () => {
  it('muestra cada proyecto con su logo y su cliente, y abre la página del proyecto', async () => {
    mockPortalAdmin()
    renderProjects()
    const playinn = await screen.findByRole('link', { name: /Playinn/ })
    expect(playinn).toHaveAttribute('href', '/monitoreo/clientes/1/proyectos/1')
    // La ruta del logo que manda el back (api/portal-admin/…) se sirve desde /api/v1/monitoring (ADR-0002 punto 10).
    expect(within(playinn).getByRole('img', { name: 'Logo de Playinn' })).toHaveAttribute('src', expect.stringContaining('/api/v1/monitoring/portal-admin/projects/1/logo?v='))

    const app = screen.getByRole('link', { name: /App de socios/ })
    expect(app).toHaveAttribute('href', '/monitoreo/clientes/2/proyectos/3')
    expect(app).toHaveTextContent('Club Olimpo')
    expect(within(app).queryByRole('img')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Clientes' })).toHaveAttribute('href', '/monitoreo/clientes')
  })

  it('separa producción de staging por el slug', async () => {
    const { state } = mockPortalAdmin()
    const playinn = state.projects[0]!
    state.projects.push({ ...playinn, id: 99, slug: 'playinn-staging', name: 'Playinn - Staging', components: [] })
    renderProjects()
    const production = await screen.findByRole('region', { name: 'Producción' })
    const staging = screen.getByRole('region', { name: 'Staging' })
    expect(within(staging).getByRole('link', { name: /Playinn - Staging/ })).toBeInTheDocument()
    expect(within(production).queryByRole('link', { name: /Playinn - Staging/ })).not.toBeInTheDocument()
    expect(within(production).getByRole('link', { name: /App de socios/ })).toBeInTheDocument()
  })

  it('al pasar el mouse muestra en qué server está cada parte y la DB con el back (D-072)', async () => {
    mockPortalAdmin()
    const user = userEvent.setup()
    renderProjects()
    await user.hover(await screen.findByRole('link', { name: /Playinn/ }))
    const card = await screen.findByRole('tooltip')
    for (const part of ['Front', 'Back', 'DB']) {
      expect((await within(card).findByText(part)).nextElementSibling).toHaveTextContent('clientes')
    }

    await user.unhover(screen.getByRole('link', { name: /Playinn/ }))
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument()
  })

  it('busca por nombre de cliente', async () => {
    mockPortalAdmin()
    const user = userEvent.setup()
    renderProjects()
    await screen.findByRole('link', { name: /Playinn/ })
    await user.type(screen.getByRole('searchbox', { name: 'Buscar proyecto o cliente' }), 'olimpo')
    expect(screen.queryByRole('link', { name: /Playinn/ })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Sitio del club/ })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /App de socios/ })).toBeInTheDocument()
  })

  it('muestra cuántas vulnerabilidades tiene cada proyecto y su desarrollador principal (D-053)', async () => {
    mockPortalAdmin()
    renderProjects()
    expect(await screen.findByRole('link', { name: /Sitio del club/ })).toHaveTextContent('2 vulnerabilidades')
    const playinn = screen.getByRole('link', { name: /Playinn/ })
    expect(playinn).toHaveTextContent('teamviolet@nexosmart.com.ar')
    expect(playinn).not.toHaveTextContent('vulnerabilidad')
  })
})
