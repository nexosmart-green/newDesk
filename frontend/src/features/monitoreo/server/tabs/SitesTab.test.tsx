import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { sites } from '../../test/fixtures/clientes'
import { mockMonitoringGet } from '../../test/mockApi'
import { renderWithProviders } from '../../test/render'
import { SitesTab } from './SitesTab'

describe('SitesTab con el vhost de clientes (muchas locations)', () => {
  beforeEach(() => mockMonitoringGet({ 'servers/5/sites': [] }))
  afterEach(() => vi.restoreAllMocks())

  it('marca los upstreams sin proceso y los cuenta por vhost', () => {
    renderWithProviders(<SitesTab serverId={5} data={sites} />)
    expect(screen.getByText('5 upstreams sin proceso (502)')).toBeInTheDocument()
    expect(screen.getByText('3 de 49 caídos')).toBeInTheDocument()
    expect(screen.getByText('2 de 2 caídos')).toBeInTheDocument()
  })

  it('colapsa las locations, muestra primero las caídas y permite filtrar', async () => {
    const user = userEvent.setup()
    renderWithProviders(<SitesTab serverId={5} data={sites} />)
    const [clientesList] = screen.getAllByRole('list', { name: 'Locations con proxy' })
    const collapsed = within(clientesList as HTMLElement).getAllByRole('listitem')
    expect(collapsed).toHaveLength(5)
    expect(collapsed[0]).toHaveTextContent('sin proceso (502)')

    await user.click(screen.getByRole('button', { name: 'Ver las 49 locations (+44)' }))
    await user.type(screen.getByLabelText('Filtrar locations'), 'tekatickets')
    const filtered = within(screen.getAllByRole('list', { name: 'Locations con proxy' })[0] as HTMLElement).getAllByRole('listitem')
    expect(filtered).toHaveLength(1)
    expect(filtered[0]).toHaveTextContent('/tekatickets/app/ → 127.0.0.1:8081')
  })
})
