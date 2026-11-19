import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { datesBetween } from '@/features/timesheet/week'
import * as peopleApi from '@/features/people/peopleApi'
import * as projectsApi from '@/features/projects/projectsApi'
import { ApiError } from '@/lib/api'
import { buildPerson, buildProject, buildTimeEntry, myEntriesPage, page } from '@/test/fixtures'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import * as hoursApi from './hoursApi'
import { MyHoursPage } from './MyHoursPage'

/** This month up to the faked today (2026-11-06), nothing else picked. */
const thisMonth = { from: '2026-11-01', to: '2026-11-06', project_id: null, search: '', user_id: null }

const recent = buildTimeEntry({ id: 301, is_running: false, ended_at: '2026-11-06T15:00:00+00:00', comment: 'Revisé el formulario' })
const old = buildTimeEntry({
  id: 302,
  is_running: false,
  started_at: '2026-10-01T12:00:00+00:00',
  ended_at: '2026-10-01T13:00:00+00:00',
  task: { id: 61, code: 'bhn-61', title: 'Viejo' },
})

function renderPage(permissions: string[]) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject()]))
  vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(page([buildPerson(), buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })]))
  const fetchEntries = vi.spyOn(hoursApi, 'fetchEntries').mockResolvedValue(myEntriesPage([recent, old], '2026-11-01T03:00:00Z', 183600))
  renderAt(<MyHoursPage />)
  return { fetchEntries }
}

describe('MyHoursPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 6, 12))
  })
  afterEach(() => vi.useRealTimers())

  it('lists my entries with their project, and no "Persona" filter without time.manage', async () => {
    const { fetchEntries } = renderPage([])

    expect(await screen.findByText('Revisé el formulario')).toBeInTheDocument()
    expect(within(screen.getByRole('list', { name: 'Registros de horas' })).getAllByText('Bahía Web')).toHaveLength(2)
    expect(screen.queryByLabelText('Persona')).not.toBeInTheDocument()
    expect(fetchEntries).toHaveBeenCalledWith(thisMonth, 1)
  })

  it('locks entries older than the window: no Editar/Borrar, a note instead', async () => {
    renderPage([])

    expect(await screen.findByRole('button', { name: 'Editar registro de bhn-60' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Editar registro de bhn-61' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Borrar registro de bhn-61' })).not.toBeInTheDocument()
    expect(screen.getByText(/Pasaron más días de los permitidos/)).toBeInTheDocument()
  })

  it('lets time.manage pick another person', async () => {
    const { fetchEntries } = renderPage(['time.manage'])

    await userEvent.selectOptions(await screen.findByLabelText('Persona'), await screen.findByRole('option', { name: 'Juan Pérez' }))

    await vi.waitFor(() => expect(fetchEntries).toHaveBeenLastCalledWith({ ...thisMonth, user_id: 2 }, 1))
  })

  it('shows the total of the whole filter, not just of the page', async () => {
    renderPage([])

    expect(await screen.findByText('51h 00m')).toBeInTheDocument()
    expect(screen.getByText(/en 2 registros/)).toBeInTheDocument()
  })

  it('refuses a reversed range before asking the server', async () => {
    const { fetchEntries } = renderPage([])
    await screen.findByText('51h 00m')
    fetchEntries.mockClear()

    const from = screen.getByLabelText('Desde')
    await userEvent.clear(from)
    await userEvent.type(from, '2026-12-01')

    expect(await screen.findByText('La fecha «Desde» no puede ser posterior a «Hasta».')).toBeInTheDocument()
    expect(fetchEntries).not.toHaveBeenCalled()
  })

  it('shows the backend reason on the date when the range is too long', async () => {
    const { fetchEntries } = renderPage([])
    await screen.findByText('51h 00m')
    fetchEntries.mockRejectedValue(new ApiError(422, 'El rango no puede superar 366 días.', { to: ['El rango no puede superar 366 días.'] }))

    const from = screen.getByLabelText('Desde')
    await userEvent.clear(from)
    await userEvent.type(from, '2024-01-01')

    expect((await screen.findAllByText('El rango no puede superar 366 días.')).length).toBeGreaterThan(0)
  })

  it('exports the CSV with the same filters and says so', async () => {
    const exportCsv = vi.spyOn(hoursApi, 'exportEntries').mockResolvedValue({ blob: new Blob(['x']), filename: 'horas.csv' })
    URL.createObjectURL = vi.fn(() => 'blob:x')
    URL.revokeObjectURL = vi.fn()
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
    renderPage([])
    await screen.findByText('51h 00m')

    await userEvent.click(screen.getByRole('button', { name: 'Exportar CSV' }))

    expect(exportCsv).toHaveBeenCalledWith(thisMonth)
    expect(await screen.findByText('Descargaste horas.csv.')).toBeInTheDocument()
    expect(click).toHaveBeenCalled()
  })

  describe('Calendario', () => {
    const worked: Record<string, number> = {
      '2026-10-27': 2 * 3600, // previous month, still in the grid
      '2026-11-03': 5 * 3600,
      '2026-11-04': 3.5 * 3600,
      '2026-11-30': 3600,
      '2026-12-01': 4 * 3600, // next month
    }

    function openCalendar() {
      const spies = renderPage([])
      const fetchDailyHours = vi.spyOn(hoursApi, 'fetchDailyHours').mockResolvedValue({
        timezone: 'America/Argentina/Buenos_Aires',
        total_seconds: 15.5 * 3600,
        days: datesBetween('2026-10-26', '2026-12-06').map((date) => ({ date, total_seconds: worked[date] ?? 0 })),
      })
      return { ...spies, fetchDailyHours }
    }

    it('shows each day, each week and only this month in the month total', async () => {
      const { fetchDailyHours } = openCalendar()

      await userEvent.click(screen.getByRole('button', { name: 'Calendario' }))

      expect(await screen.findByRole('button', { name: 'Ver registros del 3/11: 5h 00m' })).toHaveTextContent('5h 00m')
      expect(fetchDailyHours).toHaveBeenCalledWith({ user_id: null, project_id: null, from: '2026-10-26', to: '2026-12-06' })
      expect(screen.getByRole('button', { name: 'Ver registros del 5/11' })).toBeEmptyDOMElement()
      expect(within(screen.getByRole('region', { name: /semana hasta el domingo 8\/11/i })).getByText('8h 30m')).toBeInTheDocument()
      expect(within(screen.getByRole('region', { name: /semana hasta el domingo 6\/12/i })).getByText('5h 00m')).toBeInTheDocument()
      expect(screen.getByText(/Total del mes/)).toHaveTextContent('9h 30m')
      expect(screen.queryByLabelText('Desde')).not.toBeInTheDocument()
    })

    it('opens a day in the list', async () => {
      const { fetchEntries } = openCalendar()
      await userEvent.click(screen.getByRole('button', { name: 'Calendario' }))

      await userEvent.click(await screen.findByRole('button', { name: 'Ver registros del 4/11: 3h 30m' }))

      expect(screen.getByRole('button', { name: 'Lista' })).toHaveAttribute('aria-pressed', 'true')
      expect(screen.getByLabelText('Desde')).toHaveValue('2026-11-04')
      await vi.waitFor(() => expect(fetchEntries).toHaveBeenLastCalledWith({ ...thisMonth, from: '2026-11-04', to: '2026-11-04' }, 1))
    })
  })
})
