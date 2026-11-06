import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { MonitoringLayout } from './MonitoringLayout'
import { mockMonitoringGet } from './test/mockApi'
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

function renderLayout() {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo" element={<MonitoringLayout />}>
        <Route index element={<p>inicio</p>} />
      </Route>
    </Routes>,
    { route: '/monitoreo' },
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
