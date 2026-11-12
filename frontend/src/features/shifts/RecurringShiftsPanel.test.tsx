import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { ApiError } from '@/lib/api'
import { buildRecurrence } from '@/test/schedulingFixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as recurrencesApi from './recurrencesApi'
import { ShiftsPage } from './ShiftsPage'
import * as shiftsApi from './shiftsApi'
import type { ShiftTemplate } from './types'

const templates: ShiftTemplate[] = [
  { id: 3, name: 'Completo', starts_at: '09:00', ends_at: '18:00', color: '#309798', mode: 'office' },
  { id: 4, name: 'Mañana', starts_at: '09:00', ends_at: '13:00', color: '#f6beb6', mode: 'remote' },
]

async function openFixedShifts({ recurrences = [buildRecurrence()], withTemplates = templates } = {}) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions: ['shifts.manage'] }))
  vi.spyOn(shiftsApi, 'fetchShiftTemplates').mockResolvedValue(withTemplates)
  const fetchGrid = vi.spyOn(shiftsApi, 'fetchShiftGrid').mockResolvedValue({
    timezone: 'America/Argentina/Buenos_Aires',
    people: [
      { id: 1, full_name: 'Ana García', shifts: [] },
      { id: 2, full_name: 'Juan Pérez', shifts: [] },
    ],
    holidays: [],
  })
  const fetchRecurrences = vi.spyOn(recurrencesApi, 'fetchShiftRecurrences').mockResolvedValue(recurrences)
  renderRoutes([{ path: '/turnos', element: <ShiftsPage /> }], '/turnos')
  await userEvent.click(await screen.findByRole('tab', { name: 'Turnos fijos' }))
  return { fetchGrid, fetchRecurrences }
}

describe('RecurringShiftsPanel (RF-06.03)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-12T15:00:00Z'))
  })
  afterEach(() => vi.useRealTimers())

  it('lists each person rules with their days, template and range', async () => {
    await openFixedShifts({
      recurrences: [
        buildRecurrence(),
        buildRecurrence({ id: 2, template: null, weekdays: [6, 7], starts_on: '2026-12-01', ends_on: '2026-12-31' }),
        buildRecurrence({ id: 3, user: { id: 2, full_name: 'Juan Pérez' }, ends_on: '2026-11-10' }),
      ],
    })

    const list = await screen.findByRole('list', { name: 'Turnos fijos por persona' })
    const ana = within(list).getByRole('heading', { name: 'Ana García' }).closest('li') as HTMLElement
    expect(within(ana).getByRole('img', { name: 'Lun a vie' })).toBeInTheDocument()
    expect(within(ana).getByText('Completo')).toBeInTheDocument()
    expect(within(ana).getByText('Desde 02/11/2026 sin fin')).toBeInTheDocument()
    expect(within(ana).getByText('Vigente')).toBeInTheDocument()
    expect(within(ana).getByText('Plantilla eliminada: ya no genera días')).toBeInTheDocument()
    expect(within(ana).getByRole('img', { name: 'sábado y domingo' })).toBeInTheDocument()
    expect(within(ana).getByText('Empieza el 01/12/2026')).toBeInTheDocument()

    const juan = within(list).getByRole('heading', { name: 'Juan Pérez' }).closest('li') as HTMLElement
    expect(within(juan).getByText('Finalizado')).toBeInTheDocument()
    expect(within(juan).queryByRole('button', { name: /Finalizar/ })).not.toBeInTheDocument()
  })

  it('creates a rule for several people and toasts what the backend generated', async () => {
    const create = vi.spyOn(recurrencesApi, 'createShiftRecurrences').mockResolvedValue({
      message: 'Turnos fijos creados: se generaron 76 días (se saltearon 3 feriados y 2 días que ya tenían turno).',
      created: [],
    })
    const { fetchRecurrences } = await openFixedShifts({ recurrences: [] })

    await userEvent.click(await screen.findByRole('button', { name: 'Nuevo turno fijo' }))
    const dialog = screen.getByRole('dialog', { name: 'Nuevo turno fijo' })
    await userEvent.click(within(dialog).getByRole('button', { name: 'Todas' }))
    await userEvent.selectOptions(within(dialog).getByLabelText('Plantilla'), '4')
    await userEvent.click(within(dialog).getByRole('button', { name: 'viernes' }))
    await userEvent.click(within(dialog).getByRole('button', { name: 'sábado' }))
    expect(within(dialog).getByRole('button', { name: 'sábado' })).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(within(dialog).getByRole('switch', { name: /Saltear feriados/ }))
    await userEvent.click(within(dialog).getByRole('button', { name: 'Crear turno fijo' }))

    expect(create).toHaveBeenCalledWith({
      user_ids: [1, 2],
      shift_template_id: 4,
      weekdays: [1, 2, 3, 4, 6],
      starts_on: '2026-11-12',
      ends_on: null,
      skip_holidays: false,
    })
    expect(await screen.findByText(/se generaron 76 días \(se saltearon 3 feriados y 2 días que ya tenían turno\)/)).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    await vi.waitFor(() => expect(fetchRecurrences).toHaveBeenCalledTimes(2))
  })

  it('shows validation errors under their fields and keeps the dialog open', async () => {
    vi.spyOn(recurrencesApi, 'createShiftRecurrences').mockRejectedValue(
      new ApiError(422, 'Los datos enviados no son válidos.', { starts_on: ['El turno fijo no puede empezar antes de hoy.'] }),
    )
    await openFixedShifts()

    await userEvent.click(await screen.findByRole('button', { name: 'Nuevo turno fijo' }))
    const dialog = screen.getByRole('dialog', { name: 'Nuevo turno fijo' })
    await userEvent.click(within(dialog).getByLabelText('Ana García'))
    await userEvent.click(within(dialog).getByRole('button', { name: 'Lun a vie' }))
    await userEvent.click(within(dialog).getByRole('button', { name: 'Crear turno fijo' }))

    expect(await within(dialog).findByText('El turno fijo no puede empezar antes de hoy.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Desde')).toHaveAttribute('aria-invalid', 'true')
  })

  it('ends a rule on the chosen day and explains what happens with loaded days', async () => {
    const end = vi.spyOn(recurrencesApi, 'endShiftRecurrence').mockResolvedValue({ message: 'Turno fijo finalizado. Se quitaron 12 días posteriores.', removed: 12 })
    await openFixedShifts()

    await userEvent.click(await screen.findByRole('button', { name: 'Finalizar turno fijo de Ana García' }))
    const dialog = screen.getByRole('dialog', { name: 'Finalizar turno fijo de Ana García' })
    expect(within(dialog).getByText(/Los anteriores quedan como están/)).toBeInTheDocument()
    const lastDay = within(dialog).getByLabelText('Último día')
    expect(lastDay).toHaveValue('2026-11-12')
    await userEvent.clear(lastDay)
    await userEvent.type(lastDay, '2026-11-30')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Finalizar' }))

    expect(end).toHaveBeenCalledWith(1, '2026-11-30')
    expect(await screen.findByText('Turno fijo finalizado. Se quitaron 12 días posteriores.')).toBeInTheDocument()
  })

  it('deletes a rule after a confirmation that keeps past days', async () => {
    const remove = vi.spyOn(recurrencesApi, 'deleteShiftRecurrence').mockResolvedValue({ message: 'Turno fijo borrado.', removed: 30 })
    await openFixedShifts()

    await userEvent.click(await screen.findByRole('button', { name: 'Borrar turno fijo de Ana García' }))
    const dialog = screen.getByRole('dialog', { name: '¿Borrar el turno fijo de Ana García?' })
    expect(within(dialog).getByText(/Los días hasta hoy quedan cargados/)).toBeInTheDocument()
    expect(remove).not.toHaveBeenCalled()
    await userEvent.click(within(dialog).getByRole('button', { name: 'Borrar' }))

    expect(remove).toHaveBeenCalledWith(1)
    expect(await screen.findByText('Turno fijo borrado.')).toBeInTheDocument()
  })

  it('sends to the templates tab when there is none yet', async () => {
    await openFixedShifts({ recurrences: [], withTemplates: [] })

    await userEvent.click(await screen.findByRole('button', { name: 'Crear plantilla' }))

    expect(screen.getByRole('tab', { name: 'Plantillas' })).toHaveAttribute('aria-selected', 'true')
  })

  it('offers retrying when the list fails to load', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions: ['shifts.manage'] }))
    vi.spyOn(shiftsApi, 'fetchShiftTemplates').mockResolvedValue(templates)
    vi.spyOn(shiftsApi, 'fetchShiftGrid').mockResolvedValue({ timezone: 'America/Argentina/Buenos_Aires', people: [], holidays: [] })
    const fetchRecurrences = vi.spyOn(recurrencesApi, 'fetchShiftRecurrences').mockRejectedValueOnce(new ApiError(500, 'Error')).mockResolvedValue([])
    renderRoutes([{ path: '/turnos', element: <ShiftsPage /> }], '/turnos')
    await userEvent.click(await screen.findByRole('tab', { name: 'Turnos fijos' }))

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByText('Todavía no hay turnos fijos')).toBeInTheDocument()
    expect(fetchRecurrences).toHaveBeenCalledTimes(2)
  })
})
