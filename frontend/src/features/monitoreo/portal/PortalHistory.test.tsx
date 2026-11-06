import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import type { Incident } from '../api/types'
import { mockClientPortal } from '../test/clientPortalApi'
import { olimpoUser, playinnUser } from '../test/fixtures/clientPortal'
import { renderWithProviders } from '../test/render'
import { PortalProjectPage } from './PortalPages'
import { IncidentList } from './PortalHistory'

vi.mock('uplot', () => ({ default: class { setData() {} setSize() {} destroy() {} } }))
vi.stubGlobal('ResizeObserver', class { observe() {} disconnect() {} })

function renderStatus(slug: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/portal/:slug" element={<PortalProjectPage />} />
    </Routes>,
    { route: `/portal/${slug}` },
  )
}

afterEach(() => vi.restoreAllMocks())

describe('página de estado: etapas 3 y 4', () => {
  it('muestra la última actualización, los incidentes del mes con sus notas y PageSpeed', async () => {
    mockClientPortal(playinnUser)
    renderStatus('playinn')

    const update = (await screen.findByRole('heading', { name: 'Última actualización' })).closest('section') as HTMLElement
    expect(update).toHaveTextContent('Hace 3 días')
    expect(update).not.toHaveTextContent('playinn') // solo la fecha: el cliente no ve nombres de carpetas

    const history = screen.getByRole('heading', { name: 'Historial de incidentes (30 días)' }).closest('section') as HTMLElement
    const incidents = within(history).getAllByTestId('incident')
    expect(incidents).toHaveLength(1)
    // Por las partes que afecta la API, no por su nombre (D-045).
    expect(incidents[0]).toHaveTextContent(/App y CMS no respondieron el \d+\/\d+ entre las/)
    expect(incidents[0]).not.toHaveTextContent('API')
    expect(within(incidents[0] as HTMLElement).getByRole('list', { name: 'Notas del equipo' })).toHaveTextContent('Investigando: el proveedor de pagos no responde.')
    expect(history).not.toHaveTextContent('teamviolet') // el cliente no ve quién escribió

    const pagespeed = screen.getByRole('region', { name: 'PageSpeed de Web' })
    expect(within(pagespeed).getByText('Puntaje 71 de 100')).toBeInTheDocument()
    expect(within(pagespeed).getByText('Puntaje 93 de 100')).toBeInTheDocument()
    expect(within(pagespeed).getByText('Datos reales de usuarios (últimos 28 días)')).toBeInTheDocument()
    expect(within(pagespeed).getByText('Medición de laboratorio')).toBeInTheDocument()
    expect(within(pagespeed).getByText('Solo con datos reales')).toBeInTheDocument() // el laboratorio no mide INP
    expect(within(pagespeed).getByRole('img', { name: /Evolución del puntaje en celular: 60, 61/ })).toBeInTheDocument()
  })

  it('pone los incidentes abiertos primero y avisa cuando no hubo', async () => {
    mockClientPortal(olimpoUser)
    const { unmount } = renderStatus('olimpo-app')
    const incidents = await screen.findAllByTestId('incident')
    expect(incidents[0]).toHaveTextContent('En curso')
    expect(incidents[0]).toHaveTextContent(/App no responde desde el/)
    expect(incidents[1]).toHaveTextContent('Resuelto')
    unmount()

    renderStatus('olimpo-web')
    expect(await screen.findByText('Sin incidentes en los últimos 30 días.')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Rendimiento (PageSpeed)' })).not.toBeInTheDocument()
  })

  it('muestra los incidentes de a 5, con el abierto primero', async () => {
    const user = userEvent.setup()
    const incidents: Incident[] = Array.from({ length: 7 }, (_, index) => ({
      id: index + 1, component: 'Web', status: index === 6 ? 'ongoing' : 'resolved', started_at: '2026-10-20T02:00:00Z',
      ended_at: index === 6 ? null : '2026-10-20T02:01:00Z', title: `Caída ${index + 1}`, notes: [],
    }))
    render(<IncidentList incidents={incidents} empty="Sin incidentes." />)
    const shown = () => screen.getAllByTestId('incident').map((item) => within(item).getByText(/^Caída/).textContent)
    expect(shown()).toEqual(['Caída 7', 'Caída 1', 'Caída 2', 'Caída 3', 'Caída 4'])
    expect(screen.getByRole('navigation', { name: 'Paginación de incidentes' })).toHaveTextContent('Página 1 de 2 · 7 incidentes')
    await user.click(screen.getByRole('button', { name: 'Siguiente' }))
    expect(shown()).toEqual(['Caída 5', 'Caída 6'])
  })

  it('cambia el período del gráfico de tiempos pidiendo el rango al portal', async () => {
    const user = userEvent.setup()
    const { api } = mockClientPortal(playinnUser)
    renderStatus('playinn')

    // Una serie por cada parte con sitio web; la API no es una parte.
    expect(await screen.findByTestId('response-summary')).toHaveTextContent(/^Promedio en las últimas 24 h: Web \d+ ms · App \d+ ms · CMS \d+ ms$/)
    await user.click(screen.getByRole('radio', { name: '7 días' }))
    await waitFor(() => expect(screen.getByTestId('response-summary')).toHaveTextContent('Promedio en los últimos 7 días'))
    const urls = api.get.mock.calls.map(([url]) => url).filter((url) => url.includes('/response-times'))
    expect(urls).toEqual(['/portal/projects/playinn/response-times?range=24h', '/portal/projects/playinn/response-times?range=7d'])
  })
})
