import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockReports } from '../test/reportsApi'
import { renderWithProviders } from '../test/render'
import { ReportPage } from './ReportPage'
import { ReportsPage } from './ReportsPage'

function renderStaff(route: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/reportes" element={<ReportsPage />} />
      <Route path="/monitoreo/reportes/:id" element={<ReportPage />} />
    </Routes>,
    { route },
  )
}

afterEach(() => vi.restoreAllMocks())

describe('reportes del equipo', () => {
  it('arranca por los recibidos y deja ver todos', async () => {
    const user = userEvent.setup()
    const { api } = mockReports()
    renderStaff('/monitoreo/reportes')
    await screen.findByText(/La página de socios tarda/)
    const table = screen.getByRole('table')
    expect(within(table).getAllByRole('row')).toHaveLength(2) // encabezado + el de Club Olimpo
    expect(table).toHaveTextContent('Club Olimpo')
    expect(api.get).toHaveBeenCalledWith('/monitoring/portal-admin/reports?page=1&status=received')

    await user.click(screen.getByRole('tab', { name: 'Todos' }))
    expect(await screen.findByText(/No podemos cobrar/)).toBeInTheDocument()
    expect(screen.getAllByTitle('Tiene captura')).toHaveLength(1)
  })

  it('muestra el contexto del reporte y cambia su estado', async () => {
    const user = userEvent.setup()
    const { state, api } = mockReports()
    renderStaff('/monitoreo/reportes/2')
    expect(await screen.findByRole('heading', { name: 'Reporte #2', level: 1 })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Captura del reporte #2' })).toHaveAttribute('src', '/api/v1/monitoring/portal-admin/reports/2/screenshot')
    expect(screen.getAllByText(/cURL error 28/, { selector: 'div' })).toHaveLength(2)
    expect(screen.getAllByRole('link', { name: 'cliente@playinn.com' })[0]).toHaveAttribute('href', expect.stringContaining('mailto:cliente@playinn.com'))
    // El equipo ve lo técnico (la API y a qué partes afecta) y lo que vio el cliente (D-045).
    const seen = screen.getByRole('list', { name: 'Lo que vio el cliente' })
    expect(within(seen).getAllByRole('listitem').slice(1).map((item) => item.textContent)).toEqual(['WebOperativo', 'AppDegradado', 'CMSDegradado'])
    const apiRow = screen.getByRole('cell', { name: 'API' }).closest('tr') as HTMLElement
    expect(apiRow).toHaveTextContent('App y CMS')
    // Cómo estaba el servidor del proyecto al reportar (D-050).
    const atReport = screen.getByRole('region', { name: 'Servidor clientes' })
    expect(within(atReport).getByRole('link', { name: 'clientes' })).toHaveAttribute('href', '/monitoreo/servers/5')
    expect(within(atReport).getByRole('cell', { name: 'playinn-cms' }).closest('tr')).toHaveTextContent('errored')
    expect(atReport).toHaveTextContent('redis-server · failed')
    expect(atReport).toHaveTextContent('2 servicios OK')
    expect(screen.getByRole('link', { name: 'Playinn · Playinn' })).toHaveAttribute('href', '/monitoreo/clientes/1/proyectos/1')

    const group = screen.getByRole('group', { name: 'Estado del reporte' })
    expect(within(group).getByRole('button', { name: 'Resuelto' })).toHaveAttribute('aria-pressed', 'true')
    await user.click(within(group).getByRole('button', { name: 'En revisión' }))
    expect(await within(group).findByRole('button', { name: 'En revisión' })).toHaveAttribute('aria-pressed', 'true')
    expect(api.put).toHaveBeenCalledWith('/monitoring/portal-admin/reports/2', { status: 'in_review' })
    expect(state.reports.find((report) => report.id === 2)?.status).toBe('in_review')
  })

  it('explica cuando el proyecto no tiene /api/errors o no respondió', async () => {
    mockReports()
    renderStaff('/monitoreo/reportes/3')
    expect(await screen.findByText('El /api/errors del proyecto no respondió (timeout).')).toBeInTheDocument()
    expect(screen.queryByText('Servidores al reportar')).not.toBeInTheDocument()
  })

  it('marca los reportes donde el cliente escribió y el equipo no leyó', async () => {
    const { state } = mockReports()
    state.messages[3]?.push({ id: 950, author_kind: 'client', author: 'cliente@playinn.com', body: 'Obligatorio, por favor.', created_at: new Date().toISOString(), images: [] })
    renderStaff('/monitoreo/reportes?status=in_review')
    const row = (await screen.findByText(/Se puede agregar un campo de DNI/)).closest('tr') as HTMLElement
    expect(row).toHaveTextContent('Mensaje nuevo')
  })
})
