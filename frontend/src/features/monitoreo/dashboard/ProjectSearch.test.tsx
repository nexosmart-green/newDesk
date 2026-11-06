import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { IndexedProject, ServerSummary } from '../api/types'
import { mockMonitoringGet } from '../test/mockApi'
import { renderWithProviders } from '../test/render'
import { DashboardPage } from './DashboardPage'

const server: ServerSummary = {
  id: 1, name: 'reaquila', ip: '51.222.205.245', ssh_port: 1080, agent_port: 9100, enabled: true, status: 'online',
  last_seen_at: null, hostname: null, os: null, metrics: null, active_alerts: 0, agent_version: null,
}
const folder = (serverId: number, serverName: string, project: Partial<IndexedProject>): IndexedProject => ({
  server_id: serverId, server_name: serverName, server_status: 'online', path: '', name: '', type: 'monorepo', domains: [], subapps: [], linked: null, ...project,
})
// Ordenado por nombre y server, como lo devuelve GET /monitoring/inventory/projects.
const inventory: IndexedProject[] = [
  folder(2, 'cms-demo', { path: '/var/www/clubolimpo.com.ar', name: 'clubolimpo.com.ar', domains: ['www.clubolimpo.com.ar'] }),
  folder(1, 'reaquila', {
    path: '/var/www/clubolimpo.com.ar', name: 'clubolimpo.com.ar', domains: ['www.clubolimpo.com.ar'],
    linked: { project_id: 2, project_name: 'Club Olimpo', client_id: 2, client_name: 'Club Olimpo' },
  }),
  folder(5, 'clientes', { path: '/var/www/clientes.nexosmart.com.ar/playinn', name: 'playinn', subapps: ['back', 'front'] }),
]

describe('Buscador de proyectos en Servidores', () => {
  beforeEach(() => mockMonitoringGet({ servers: [server], 'inventory/projects': inventory }))
  afterEach(() => vi.restoreAllMocks())

  it('encuentra el proyecto por dominio y lleva a su pestaña filtrada', async () => {
    const user = userEvent.setup()
    renderWithProviders(<DashboardPage />)

    await user.type(await screen.findByRole('searchbox', { name: 'Buscar proyecto' }), 'clubolimpo.com')
    const links = await screen.findAllByRole('link', { name: 'clubolimpo.com.ar' })
    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/monitoreo/servers/2/proyectos?q=clubolimpo.com.ar',
      '/monitoreo/servers/1/proyectos?q=clubolimpo.com.ar',
    ])
    expect(screen.getAllByText('www.clubolimpo.com.ar')).toHaveLength(2)
    expect(screen.getByText('Proyectos · 2')).toBeInTheDocument()
    // La carpeta de reaquila está vinculada a Club Olimpo (D-044); la de cms-demo, no.
    const tags = screen.getAllByRole('link', { name: 'Cliente: Club Olimpo' })
    expect(tags).toHaveLength(1)
    expect(tags[0]).toHaveAttribute('href', '/monitoreo/clientes/2/proyectos/2')
  })

  it('busca sin acentos ni mayúsculas y avisa si no hay nada', async () => {
    const user = userEvent.setup()
    renderWithProviders(<DashboardPage />, { route: '/monitoreo?q=PLAYÍNN' })
    expect(await screen.findByRole('link', { name: 'playinn' })).toBeInTheDocument()

    const box = screen.getByRole('searchbox', { name: 'Buscar proyecto' })
    await user.clear(box)
    await user.type(box, 'no-existe-xyz')
    expect(await screen.findByText('Ningún proyecto coincide con "no-existe-xyz".')).toBeInTheDocument()
  })
})
