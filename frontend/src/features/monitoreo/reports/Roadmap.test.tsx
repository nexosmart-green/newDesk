import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockReports } from '../test/reportsApi'
import { renderWithProviders } from '../test/render'
import { ReportPage } from './ReportPage'
import { RoadmapPanel } from './Roadmap'
import { ReportsPage } from './ReportsPage'

function renderWithRoadmap(route: string) {
  return renderWithProviders(
    <>
      <Routes>
        <Route path="/monitoreo/reportes" element={<ReportsPage />} />
        <Route path="/monitoreo/reportes/:id" element={<ReportPage />} />
      </Routes>
      <aside aria-label="Roadmap">
        <RoadmapPanel onClose={() => {}} />
      </aside>
    </>,
    { route },
  )
}

const roadmap = () => screen.getByRole('complementary', { name: 'Roadmap' })
const ids = (list: HTMLElement) => within(list).getAllByRole('listitem').map((item) => item.textContent?.match(/#(\d+)/)?.[1])
const mine = () => within(roadmap()).getByRole('list', { name: 'Mi roadmap' })

beforeEach(() => localStorage.clear())
afterEach(() => vi.restoreAllMocks())

describe('roadmap de reportes', () => {
  it('se suma desde el reporte y desde la lista, se ordena y se saca', async () => {
    const user = userEvent.setup()
    const { api } = mockReports()
    renderWithRoadmap('/monitoreo/reportes/2')
    expect(await within(roadmap()).findByText(/Sumá reportes desde la lista/)).toBeInTheDocument()

    await user.click(await screen.findByRole('button', { name: 'Sumar a mi roadmap' }))
    expect(await screen.findByRole('button', { name: 'En tu roadmap' })).toHaveAttribute('aria-pressed', 'true')
    expect(ids(mine())).toEqual(['2'])
    expect(api.post).toHaveBeenCalledWith('/monitoring/portal-admin/roadmap', { report_id: 2 })

    await user.click(screen.getByRole('link', { name: /Reportes/ }))
    await screen.findByText(/La página de socios tarda/)
    await user.click(screen.getByRole('button', { name: 'Sumar a mi roadmap' })) // la fila del #1, el único recibido
    await within(roadmap()).findByText(/#1 Club Olimpo/)
    expect(ids(mine())).toEqual(['2', '1'])

    await user.click(within(mine()).getByRole('button', { name: 'Subir el #1' }))
    await within(mine()).findByRole('button', { name: 'Bajar el #2' })
    expect(ids(mine())).toEqual(['1', '2'])
    expect(api.put).toHaveBeenCalledWith('/monitoring/portal-admin/roadmap', { report_ids: [1, 2] })

    await user.click(within(mine()).getByRole('button', { name: 'Sacar el #2 del roadmap' }))
    await waitFor(() => expect(ids(mine())).toEqual(['1']))
    expect(api.delete).toHaveBeenCalledWith('/monitoring/portal-admin/roadmap/2')
  })

  // D-066: es personal. El back solo devuelve el de quien pregunta; acá no hay forma de elegir otro dev.
  it('es personal: no se ve el de otro dev, y un clic abre el reporte flotante', async () => {
    const user = userEvent.setup()
    mockReports()
    renderWithRoadmap('/monitoreo/reportes')
    expect(await within(roadmap()).findByText(/Sumá reportes desde la lista/)).toBeInTheDocument()
    expect(within(roadmap()).queryByRole('combobox')).not.toBeInTheDocument()
    expect(within(roadmap()).queryByText(/#3/)).not.toBeInTheDocument()

    await user.click(await screen.findByRole('button', { name: 'Sumar a mi roadmap' }))
    await user.click(await within(mine()).findByRole('button', { name: /#1 Club Olimpo/ }))
    expect(await screen.findByRole('dialog', { name: /#1/ })).toBeInTheDocument()
  })
})
