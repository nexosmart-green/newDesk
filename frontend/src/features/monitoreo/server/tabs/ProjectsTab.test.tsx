import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { projects } from '../../test/fixtures/clientes'
import type { IndexedProject } from '../../api/types'
import { mockMonitoringGet } from '../../test/mockApi'
import { renderWithProviders } from '../../test/render'
import { ProjectsTab } from './ProjectsTab'

describe('ProjectsTab: filtro', () => {
  it('arranca con el ?q del buscador de Servidores', () => {
    renderWithProviders(<ProjectsTab data={projects} />, { route: '/monitoreo/servers/5/proyectos?q=tooki' })
    expect(screen.getByRole('searchbox', { name: 'Filtrar proyectos' })).toHaveValue('tooki')
    expect(screen.getByText(/^Proyectos · 4 de \d+$/)).toBeInTheDocument()
    expect(screen.getAllByRole('row').slice(1).every((row) => row.textContent?.includes('tooki'))).toBe(true)
  })

  it('busca también por subapp y avisa si no hay coincidencias', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ProjectsTab data={projects} />)
    const box = screen.getByRole('searchbox', { name: 'Filtrar proyectos' })
    await user.type(box, 'apilaravel')
    expect(screen.getByText('dev.reaquila.com')).toBeInTheDocument()
    await user.clear(box)
    await user.type(box, 'nada-por-aca')
    expect(screen.getByText('Ningún proyecto coincide con "nada-por-aca".')).toBeInTheDocument()
  })
})

describe('ProjectsTab: de qué cliente es cada carpeta (D-044)', () => {
  it('marca las carpetas vinculadas a un proyecto de cliente', async () => {
    const playinn: IndexedProject = {
      server_id: 5, server_name: 'clientes', server_status: 'online', path: '/var/www/clientes.nexosmart.com.ar/playinn', name: 'playinn', type: 'monorepo',
      domains: [], subapps: [], linked: { project_id: 1, project_name: 'Playinn', client_id: 1, client_name: 'Playinn' },
    }
    // La misma carpeta en otro server no cuenta: el vínculo es por server.
    mockMonitoringGet({ 'inventory/projects': [playinn, { ...playinn, server_id: 1, path: '/var/www/clientes.nexosmart.com.ar/tooki-web' }] })
    renderWithProviders(<ProjectsTab data={projects} serverId={5} />)
    const tag = await screen.findByRole('link', { name: 'Cliente: Playinn' })
    expect(tag).toHaveAttribute('href', '/monitoreo/clientes/1/proyectos/1')
    expect(tag.closest('tr')).toHaveTextContent('playinn')
    expect(screen.getAllByRole('link', { name: /^Cliente:/ })).toHaveLength(1)
  })
})

afterEach(() => vi.restoreAllMocks())
