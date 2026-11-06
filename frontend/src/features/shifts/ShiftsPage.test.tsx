import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { ShiftsPage } from './ShiftsPage'
import * as shiftsApi from './shiftsApi'
import type { Shift, ShiftGrid, ShiftTemplate } from './types'

const templates: ShiftTemplate[] = [
  { id: 3, name: 'Completo', starts_at: '09:00', ends_at: '18:00', color: '#4f8cff', mode: 'office' },
  { id: 4, name: 'Mañana', starts_at: '09:00', ends_at: '13:00', color: '#3ecf8e', mode: 'remote' },
]

const mondayShift: Shift = {
  id: 70,
  date: '2026-11-02',
  name: 'Completo',
  color: '#4f8cff',
  starts_at: '09:00',
  ends_at: '18:00',
  mode: 'office',
  mode_label: 'Oficina',
  start_utc: '2026-11-02T12:00:00+00:00',
  end_utc: '2026-11-02T21:00:00+00:00',
}

const grid: ShiftGrid = {
  timezone: 'America/Argentina/Buenos_Aires',
  people: [
    { id: 1, full_name: 'Ana García', shifts: [mondayShift] },
    { id: 2, full_name: 'Juan Pérez', shifts: [] },
  ],
  holidays: [{ id: 8, date: '2026-11-05', name: 'Día del equipo', scope: 'company' }],
}

function renderShifts(permissions = ['shifts.manage']) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  const fetchGrid = vi.spyOn(shiftsApi, 'fetchShiftGrid').mockResolvedValue(grid)
  vi.spyOn(shiftsApi, 'fetchShiftTemplates').mockResolvedValue(templates)
  renderRoutes([{ path: '/turnos', element: <ShiftsPage /> }], '/turnos')
  return fetchGrid
}

const cell = (name: RegExp) => screen.getByRole('button', { name })

describe('ShiftsPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 6, 12))
  })
  afterEach(() => vi.useRealTimers())

  it('renders each person × day with its shift and the holidays in the header (RF-06.02)', async () => {
    const fetchGrid = renderShifts()

    expect(await screen.findByRole('button', { name: /Ana García, lunes 2\/11: Completo 09:00–18:00 \(Oficina\)/ })).toBeInTheDocument()
    expect(fetchGrid).toHaveBeenCalledWith('2026-11-02', '2026-11-08')
    expect(cell(/Juan Pérez, lunes 2\/11: sin turno/)).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: /jueves 5\/11.*Feriado: Día del equipo/ })).toBeInTheDocument()
  })

  it('assigns a template to several selected cells, one request per weekday set', async () => {
    const assign = vi.spyOn(shiftsApi, 'assignShifts').mockResolvedValue(1)
    renderShifts()

    await userEvent.click(await screen.findByRole('button', { name: /Ana García, martes 3\/11/ }))
    await userEvent.click(cell(/Juan Pérez, martes 3\/11/))
    await userEvent.click(cell(/Juan Pérez, miércoles 4\/11/))
    expect(screen.getByText('3 días seleccionados')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Asignar turno' }))
    const dialog = screen.getByRole('dialog', { name: 'Asignar turno a 3 días' })
    await userEvent.selectOptions(within(dialog).getByLabelText('Turno'), '4')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Aplicar' }))

    const week = { from: '2026-11-02', to: '2026-11-08' }
    expect(assign).toHaveBeenCalledWith({ user_ids: [1], shift_template_id: 4, ...week, weekdays: [2] })
    expect(assign).toHaveBeenCalledWith({ user_ids: [2], shift_template_id: 4, ...week, weekdays: [2, 3] })
  })

  it('bulk assigns people × date range × weekdays', async () => {
    const assign = vi.spyOn(shiftsApi, 'assignShifts').mockResolvedValue(8)
    renderShifts()

    await userEvent.click(await screen.findByRole('button', { name: 'Asignación masiva' }))
    const dialog = screen.getByRole('dialog', { name: 'Asignación masiva' })
    await userEvent.click(within(dialog).getByLabelText('Ana García'))
    await userEvent.click(within(dialog).getByLabelText('Juan Pérez'))
    await userEvent.click(within(dialog).getByLabelText('Vie'))
    await userEvent.click(within(dialog).getByRole('button', { name: 'Asignar' }))

    expect(assign).toHaveBeenCalledWith({
      user_ids: [1, 2],
      shift_template_id: 3,
      from: '2026-11-02',
      to: '2026-11-08',
      weekdays: [1, 2, 3, 4],
    })
    expect(await screen.findByText('Listo: 8 días actualizados.')).toBeInTheDocument()
  })

  it('copies the previous week only after confirming', async () => {
    const copy = vi.spyOn(shiftsApi, 'copyPreviousWeek').mockResolvedValue(5)
    renderShifts()

    await userEvent.click(await screen.findByRole('button', { name: 'Copiar semana anterior' }))
    expect(copy).not.toHaveBeenCalled()
    await userEvent.click(within(screen.getByRole('dialog', { name: 'Copiar semana anterior' })).getByRole('button', { name: 'Copiar' }))

    expect(copy).toHaveBeenCalledWith('2026-11-02')
    expect(await screen.findByText('Se copiaron 5 turnos.')).toBeInTheDocument()
  })

  it('does not show the grid without shifts.manage', async () => {
    renderShifts([])

    expect(await screen.findByText('No tenés acceso a los turnos del equipo.')).toBeInTheDocument()
  })
})
