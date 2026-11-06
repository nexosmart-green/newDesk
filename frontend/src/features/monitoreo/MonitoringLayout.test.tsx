import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { MonitoringLayout } from './MonitoringLayout'
import { initialReports } from './test/fixtures/reports'
import { mockMonitoringApi, mockMonitoringGet } from './test/mockApi'
import { renderWithProviders } from './test/render'

vi.mock('./realtime/useEvents', () => ({ useEvents: () => 'open' }))

beforeEach(() => {
  mockMonitoringGet({ servers: [] })
  // jsdom no trae matchMedia; el cajón lo consulta para cerrarse al pasar a escritorio.
  vi.stubGlobal('matchMedia', () => ({ matches: false, addEventListener() {}, removeEventListener() {} }))
})
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

function renderLayout({ admin = false } = {}) {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo" element={<MonitoringLayout />}>
        <Route index element={<p>inicio</p>} />
      </Route>
    </Routes>,
    { route: '/monitoreo', admin },
  )
}

it('el cajón móvil lleva el foco adentro, cierra con Escape y lo devuelve al botón', async () => {
  const user = userEvent.setup()
  renderLayout()
  const menu = screen.getByRole('button', { name: 'Abrir menú' })
  await user.click(menu)
  expect(menu).toHaveAttribute('aria-expanded', 'true')
  expect(screen.getByRole('link', { name: 'Servidores' })).toHaveFocus()

  await user.keyboard('{Escape}')
  expect(menu).toHaveAttribute('aria-expanded', 'false')
  expect(menu).toHaveFocus()
})

it('aplica la piel solo a su subárbol y muestra el estado de la conexión', () => {
  renderLayout()
  expect(screen.getByText('inicio').closest('[data-skin="monitoreo"]')).not.toBeNull()
  expect(screen.getByRole('status')).toHaveTextContent('En vivo')
  // Sin monitoring.manage no hay Configuración.
  expect(screen.queryByRole('link', { name: 'Configuración' })).not.toBeInTheDocument()
})

it('suma Proyectos y Reportes al menú, con los reportes para mirar, y la columna del roadmap en escritorio', async () => {
  const user = userEvent.setup()
  localStorage.clear()
  vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener() {}, removeEventListener() {} }))
  const [report] = initialReports()
  mockMonitoringApi({
    'GET servers': [],
    'GET portal-admin/reports/attention': { count: 2 },
    'GET portal-admin/roadmap': [{ ...report, unread: false }],
    'GET daily-report/status': { available: false, reason: 'Claude no está conectado.' },
  })
  renderLayout({ admin: true })

  const menu = screen.getByRole('navigation', { name: 'Monitoreo' })
  expect(within(menu).getAllByRole('link').map((link) => link.textContent?.replace(/\d+$/, ''))).toEqual(['Servidores', 'Alertas', 'Proyectos', 'Reportes', 'Configuración'])
  expect(within(menu).getByRole('link', { name: /^Proyectos/ })).toHaveAttribute('href', '/monitoreo/proyectos')
  const reports = within(menu).getByRole('link', { name: /^Reportes/ })
  expect(reports).toHaveAttribute('href', '/monitoreo/reportes')
  expect(await within(reports).findByLabelText('2 para mirar')).toHaveTextContent('2')

  // El botón de la barra del celular está siempre (lo oculta el CSS); el flotante de escritorio aparece al cerrar la columna.
  const toggles = () => screen.getAllByRole('button', { name: 'Abrir el roadmap (1 pendientes)' })
  const column = screen.getByRole('complementary', { name: 'Roadmap' })
  expect(await within(column).findByRole('button', { name: /#1 Club Olimpo/ })).toBeInTheDocument()
  expect(toggles()).toHaveLength(1)
  await user.click(within(column).getByRole('button', { name: 'Cerrar el roadmap' }))
  expect(screen.queryByRole('complementary', { name: 'Roadmap' })).not.toBeInTheDocument()
  expect(toggles()).toHaveLength(2)
  expect(localStorage.getItem('mon-roadmap-open')).toBe('0')
})
