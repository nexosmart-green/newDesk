import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as timerApi from '@/features/timer/timerApi'
import { buildTimeEntry } from '@/test/fixtures'
import { renderRoutes } from '@/test/renderWithProviders'
import { MyWeekPage } from './MyWeekPage'
import * as timesheetApi from './timesheetApi'
import type { Timesheet, TimesheetEntry } from './types'

function entry(overrides: Partial<TimesheetEntry> = {}): TimesheetEntry {
  return {
    id: 1,
    task: { code: 'bhn-60', title: 'Arreglar el login' },
    project: 'Bahía Web',
    started_at: '2026-11-02T12:00:00+00:00',
    ended_at: '2026-11-02T14:30:00+00:00',
    seconds: 9000,
    is_running: false,
    comment: 'Corregí la validación del formulario',
    comment_pending: false,
    source: 'timer',
    ...overrides,
  }
}

function buildTimesheet(): Timesheet {
  const dates = ['02', '03', '04', '05', '06', '07', '08']
  return {
    timezone: 'America/Argentina/Buenos_Aires',
    total_seconds: 12600,
    days: dates.map((day) => ({ date: `2026-11-${day}`, total_seconds: 0, entries: [] })).map((day) => {
      if (day.date === '2026-11-02') return { ...day, total_seconds: 9000, entries: [entry()] }
      if (day.date === '2026-11-03') {
        return {
          ...day,
          total_seconds: 3600,
          entries: [
            entry({
              id: 2,
              started_at: '2026-11-03T12:00:00+00:00',
              ended_at: '2026-11-03T13:00:00+00:00',
              seconds: 3600,
              comment: null,
              comment_pending: true,
            }),
          ],
        }
      }
      return day
    }),
  }
}

function renderWeek(path = '/mi-semana') {
  const fetch = vi.spyOn(timesheetApi, 'fetchTimesheet').mockResolvedValue(buildTimesheet())
  renderRoutes([{ path: '/mi-semana', element: <MyWeekPage /> }], path)
  return fetch
}

describe('MyWeekPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 6, 12))
  })
  afterEach(() => vi.useRealTimers())

  it('shows week and daily totals with comments for the current Monday–Sunday', async () => {
    const fetch = renderWeek()

    expect(await screen.findByText('3h 30m')).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith('2026-11-02', '2026-11-08')
    const monday = screen.getByRole('region', { name: /lunes 2\/11/i })
    expect(within(monday).getByLabelText('Total del día 2h 30m')).toBeInTheDocument()
    expect(within(monday).getByText('Corregí la validación del formulario')).toBeInTheDocument()
    const tuesday = screen.getByRole('region', { name: /martes 3\/11/i })
    expect(within(tuesday).getByLabelText('Total del día 1h 00m')).toBeInTheDocument()
    expect(within(tuesday).getByText('Comentario pendiente')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: /miércoles 4\/11/i })).getByText('Sin registros')).toBeInTheDocument()
  })

  it('moves to the previous week', async () => {
    const fetch = renderWeek()
    await screen.findByText('3h 30m')

    await userEvent.click(screen.getByRole('button', { name: '← Semana anterior' }))

    await vi.waitFor(() => expect(fetch).toHaveBeenCalledWith('2026-10-26', '2026-11-01'))
  })

  it('opens ?revisar=<id> and completes the pending comment (RF-05.17)', async () => {
    const complete = vi
      .spyOn(timerApi, 'completeEntryComment')
      .mockResolvedValue(buildTimeEntry({ id: 2, is_running: false, comment: 'Revisé los logs del deploy' }))
    renderWeek('/mi-semana?revisar=2')

    const dialog = await screen.findByRole('dialog', { name: 'Completá el comentario' })
    await userEvent.type(within(dialog).getByLabelText('¿Qué hiciste?'), 'Revisé los logs del deploy')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Guardar comentario' }))

    expect(complete).toHaveBeenCalledWith(2, 'Revisé los logs del deploy')
  })
})
