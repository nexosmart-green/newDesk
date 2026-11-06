import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockReports } from '../test/reportsApi'
import { renderWithProviders } from '../test/render'
import { FLOATING_KEY } from './floating'
import { ReportPage } from './ReportPage'

function renderReport(route: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/reportes/:id" element={<ReportPage />} />
      <Route path="/otra" element={<p>otra pantalla</p>} />
    </Routes>,
    { route },
  )
}

beforeEach(() => {
  localStorage.clear()
  mockReports()
})
afterEach(() => vi.restoreAllMocks())

describe('reporte en ventana flotante', () => {
  it('se abre desde el reporte, se minimiza al dock, se restaura y se cierra', async () => {
    const user = userEvent.setup()
    renderReport('/monitoreo/reportes/2')
    await user.click(await screen.findByRole('button', { name: 'Abrir flotante' }))

    const dialog = await screen.findByRole('dialog', { name: '#2 · Playinn · Playinn' })
    expect(within(dialog).getByRole('group', { name: 'Estado del reporte' })).toBeInTheDocument()
    expect(within(dialog).getByRole('list', { name: 'Conversación' })).toHaveTextContent('No podemos cobrar')
    expect(within(dialog).getByRole('link', { name: 'Ver el reporte completo' })).toHaveAttribute('href', '/monitoreo/reportes/2')
    expect(JSON.parse(localStorage.getItem(FLOATING_KEY) ?? '[]')).toMatchObject([{ id: 2, minimized: false }])

    await user.click(within(dialog).getByRole('button', { name: 'Minimizar' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    const dock = screen.getByRole('navigation', { name: 'Reportes minimizados' })
    await user.click(within(dock).getByRole('button', { name: /#2 · Playinn/ }))
    expect(await screen.findByRole('dialog', { name: /#2/ })).toBeInTheDocument()

    await user.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cerrar' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(localStorage.getItem(FLOATING_KEY)).toBeNull()
  })

  it('vuelve abierta después de recargar, en cualquier pantalla', async () => {
    localStorage.setItem(FLOATING_KEY, JSON.stringify([{ id: 3, x: 100, y: 100, minimized: false, z: 1 }]))
    renderReport('/otra')
    expect(screen.getByText('otra pantalla')).toBeInTheDocument()
    const dialog = await screen.findByRole('dialog', { name: '#3 · Playinn · Playinn' })
    expect(dialog).toHaveTextContent('campo de DNI')
  })
})
