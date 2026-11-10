import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as peopleApi from '@/features/people/peopleApi'
import * as projectsApi from '@/features/projects/projectsApi'
import { ApiError } from '@/lib/api'
import { buildPerson, buildProject, page } from '@/test/fixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as historyApi from './historyApi'
import { MyWeekPage } from './MyWeekPage'
import type { HistoryEntry } from './types'

const entry: HistoryEntry = {
  id: 1,
  user: { id: 1, full_name: 'Ana García' },
  task: { code: 'bhn-60', title: 'Arreglar el login' },
  project: { id: 9, name: 'Bahía Web' },
  started_at: '2026-11-03T12:00:00+00:00',
  ended_at: '2026-11-03T14:30:00+00:00',
  seconds: 9000,
  is_running: false,
  comment: 'Corregí la validación',
  comment_pending: false,
  source: 'manual',
}

function renderHistory(permissions: string[] = []) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject({ id: 9, name: 'Bahía Web' })]))
  vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(page([buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })]))
  const fetch = vi.spyOn(historyApi, 'fetchMyTimeEntries').mockResolvedValue({
    data: [entry],
    meta: { current_page: 1, last_page: 2, per_page: 50, total: 51, total_seconds: 183600 },
  })
  renderRoutes([{ path: '/mi-semana', element: <MyWeekPage /> }], '/mi-semana?vista=historial')
  return fetch
}

describe('Mi semana → Historial', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-10T15:00:00Z'))
  })
  afterEach(() => vi.useRealTimers())

  it('defaults to this month, shows the filter total and the entries', async () => {
    const fetch = renderHistory()

    expect(await screen.findByText('51h 00m')).toBeInTheDocument()
    expect(screen.getByText(/51 registros/)).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith({ from: '2026-11-01', to: '2026-11-10', project_id: null, search: '', user_id: null }, 1)
    const list = screen.getByRole('list', { name: 'Registros de horas' })
    expect(within(list).getByText('Corregí la validación')).toBeInTheDocument()
    expect(within(list).getByRole('link', { name: 'bhn-60' })).toHaveAttribute('href', '/tareas/bhn-60')
    expect(screen.queryByLabelText('Persona')).not.toBeInTheDocument()
  })

  it('refuses a reversed range before asking the server', async () => {
    const fetch = renderHistory()
    await screen.findByText('51h 00m')
    fetch.mockClear()

    const from = screen.getByLabelText('Desde')
    await userEvent.clear(from)
    await userEvent.type(from, '2026-12-01')

    expect(await screen.findByText('La fecha «Desde» no puede ser posterior a «Hasta».')).toBeInTheDocument()
    expect(fetch).not.toHaveBeenCalled()
  })

  it('with time.manage picks a person', async () => {
    const fetch = renderHistory(['time.manage'])
    await screen.findByRole('option', { name: 'Juan Pérez' })

    await userEvent.selectOptions(screen.getByLabelText('Persona'), 'Juan Pérez')

    await vi.waitFor(() => expect(fetch).toHaveBeenLastCalledWith(expect.objectContaining({ user_id: 2 }), 1))
  })

  it('exports the CSV with the same filters and says so', async () => {
    const exportCsv = vi.spyOn(historyApi, 'exportMyTimeEntries').mockResolvedValue({ blob: new Blob(['x']), filename: 'horas.csv' })
    URL.createObjectURL = vi.fn(() => 'blob:x')
    URL.revokeObjectURL = vi.fn()
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
    renderHistory()
    await screen.findByText('51h 00m')

    await userEvent.click(screen.getByRole('button', { name: 'Exportar CSV' }))

    expect(exportCsv).toHaveBeenCalledWith({ from: '2026-11-01', to: '2026-11-10', project_id: null, search: '', user_id: null })
    expect(await screen.findByText('Descargaste horas.csv.')).toBeInTheDocument()
    expect(click).toHaveBeenCalled()
  })

  it('shows the backend reason when the range is too long', async () => {
    renderHistory()
    vi.spyOn(historyApi, 'fetchMyTimeEntries').mockRejectedValue(
      new ApiError(422, 'El rango no puede superar 366 días.', { to: ['El rango no puede superar 366 días.'] }),
    )
    await screen.findByText('51h 00m')

    const from = screen.getByLabelText('Desde')
    await userEvent.clear(from)
    await userEvent.type(from, '2024-01-01')

    expect((await screen.findAllByText('El rango no puede superar 366 días.')).length).toBeGreaterThan(0)
  })
})
